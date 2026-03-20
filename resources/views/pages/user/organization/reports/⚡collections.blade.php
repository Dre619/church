<?php

use App\Exports\CollectionsExport;
use App\Models\Organization;
use App\Models\Payments;
use App\Models\PaymentCategory;
use App\Models\User;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use WireUi\Traits\WireUiActions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    use WireUiActions;

    // ── Plan gates ────────────────────────────────────────────────────────────
    public bool $canViewReports = true;
    public bool $canExport      = true;

    // ── Currency ──────────────────────────────────────────────────────────────
    public string $currency = 'ZMW';

    // ── Filters ───────────────────────────────────────────────────────────────
    public string $year  = '';
    public string $month = '';

    // ── Accordion state ───────────────────────────────────────────────────────
    public ?int $activeCategory = null;

    // ── Offering category names (identify by name, case-insensitive) ──────────
    // The four "payment" categories: Tithe, Offering, Donation, Other/Welfare etc.
    // Only "Offering" gets the Sunday/Midweek split. The rest get member breakdown.
    private array $offeringNames = ['offering', 'offerings'];

    public function mount(): void
    {
        $this->year  = now()->format('Y');
        $this->month = '';

        $org = auth()->user()->myOrganization->organization_id;
        $this->currency = Organization::find($org)?->currency ?? 'ZMW';

        $plan = active_plan();
        $this->canViewReports = $plan?->can_view_reports ?? true;
        $this->canExport      = $plan?->can_export ?? true;
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getAvailableYearsProperty(): array
    {
        $org = auth()->user()->myOrganization->organization_id;

        return Payments::where('organization_id', $org)
            ->selectRaw('YEAR(donation_date) as yr')
            ->groupBy('yr')
            ->orderByDesc('yr')
            ->pluck('yr')
            ->toArray();
    }

    /** All active payment categories for this org */
    public function getCategoriesProperty(): Collection
    {
        $org = auth()->user()->myOrganization->organization_id;

        return PaymentCategory::where('organization_id', $org)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Identify the "Offering" category object (the one that gets Sunday/Midweek split).
     */
    public function getOfferingCategoryProperty(): ?PaymentCategory
    {
        return $this->categories->first(
            fn ($c) => in_array(strtolower($c->name), $this->offeringNames)
        );
    }

    /**
     * Offering category: Sunday vs Midweek totals, one row per month.
     * Sunday = DAYOFWEEK() = 1 in MySQL.
     */
    public function getOfferingsDataProperty(): Collection
    {
        $cat = $this->offeringCategory;
        if (! $cat) return collect();

        $org = auth()->user()->myOrganization->organization_id;

        return Payments::where('organization_id', $org)
            ->where('category_id', $cat->id)
            ->whereYear('donation_date', $this->year)
            ->when($this->month, fn ($q) => $q->whereMonth('donation_date', $this->month))
            ->selectRaw("
                MONTH(donation_date)                                              AS month_num,
                MONTHNAME(donation_date)                                          AS month_name,
                SUM(CASE WHEN DAYOFWEEK(donation_date) = 1 THEN amount ELSE 0 END) AS sunday_total,
                SUM(CASE WHEN DAYOFWEEK(donation_date) != 1 THEN amount ELSE 0 END) AS midweek_total,
                SUM(amount)                                                        AS monthly_total
            ")
            ->groupByRaw('MONTH(donation_date), MONTHNAME(donation_date)')
            ->orderByRaw('MONTH(donation_date)')
            ->get();
    }

    /**
     * Non-offering categories: total per member per month.
     * Returns [category_id => [member_name => [month_num => total]]]
     */
    public function getCategoryMemberDataProperty(): array
    {
        if (! $this->activeCategory) return [];

        $org = auth()->user()->myOrganization->organization_id;

        $rows = Payments::where('payments.organization_id', $org)
            ->where('payments.category_id', $this->activeCategory)
            ->whereYear('donation_date', $this->year)
            ->when($this->month, fn ($q) => $q->whereMonth('donation_date', $this->month))
            ->join('users', 'payments.user_id', '=', 'users.id')
            ->selectRaw("
                users.id                   AS user_id,
                users.name                 AS member_name,
                MONTH(donation_date)       AS month_num,
                MONTHNAME(donation_date)   AS month_name,
                SUM(payments.amount)       AS total
            ")
            ->groupByRaw('users.id, users.name, MONTH(donation_date), MONTHNAME(donation_date)')
            ->orderByRaw('users.name, MONTH(donation_date)')
            ->get();

        // Structure: member_name => [month_num => total]
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->member_name][$row->month_num] = $row->total;
        }

        return $grouped;
    }

    /** Grand total per category for the period (for summary cards) */
    public function getCategoryTotalsProperty(): Collection
    {
        $org = auth()->user()->myOrganization->organization_id;

        return Payments::where('payments.organization_id', $org)
            ->whereYear('donation_date', $this->year)
            ->when($this->month, fn ($q) => $q->whereMonth('donation_date', $this->month))
            ->join('payment_categories', 'payments.category_id', '=', 'payment_categories.id')
            ->selectRaw('payment_categories.id, payment_categories.name, SUM(payments.amount) as total')
            ->groupBy('payment_categories.id', 'payment_categories.name')
            ->orderBy('payment_categories.name')
            ->get()
            ->keyBy('id');
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function updatingYear(): void  { $this->activeCategory = null; }
    public function updatingMonth(): void { $this->activeCategory = null; }

    public function setActiveCategory(?int $id): void
    {
        $this->activeCategory = ($this->activeCategory === $id) ? null : $id;
    }

    public function exportExcel(): mixed
    {
        if (! $this->canExport) {
            $this->notification([
                'title'       => 'Upgrade required',
                'description' => 'Excel exports are available on the Standard plan and above.',
                'icon'        => 'lock-closed',
                'iconColor'   => 'text-amber-500',
            ]);

            return null;
        }

        $orgId    = auth()->user()->myOrganization->organization_id;
        $filename = 'collections-report-' . $this->year . ($this->month ? '-' . $this->month : '') . '.xlsx';

        return Excel::download(new CollectionsExport($orgId, $this->year, $this->month), $filename);
    }
};
?>

<div class="min-h-screen bg-gray-50 p-6">

    @if(! $canViewReports)
        <x-upgrade-gate
            title="Reports not available on your plan"
            description="Upgrade to the Starter plan or higher to unlock collections, expenses, and pledge reports."
        />
    @else

    {{-- ── Header ───────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Collection Report</h1>
            <p class="mt-1 text-sm text-gray-500">Breakdown by category for {{ $year }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-select wire:model.live="year" class="w-28"
                :options="collect($this->availableYears)->map(fn ($yr) => ['value' => $yr, 'label' => $yr])->toArray()"
                option-value="value" option-label="label"
            />

            <x-select wire:model.live="month" class="w-36" placeholder="All months"
                :options="collect(range(1,12))->map(fn ($m) => ['value' => str_pad($m,2,'0',STR_PAD_LEFT), 'label' => \Carbon\Carbon::create()->month($m)->format('F')])->toArray()"
                option-value="value" option-label="label"
            />

            <x-button
                wire:click="exportExcel"
                wire:loading.attr="disabled"
                icon="arrow-down-tray"
                label="Export Excel"
                positive
                sm
            />
        </div>
    </div>

    {{-- ── Summary Cards ────────────────────────────────────────────────────── --}}
    @if ($this->categoryTotals->isNotEmpty())
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($this->categoryTotals as $ct)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="truncate text-xs font-medium uppercase tracking-wide text-gray-400">{{ $ct->name }}</p>
                    <p class="mt-1 text-xl font-bold text-gray-900">{{ format_currency($ct->total, $this->currency) }}</p>
                </div>
            @endforeach
            <div class="rounded-xl border border-primary-200 bg-primary-50 p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-primary-500">Grand Total</p>
                <p class="mt-1 text-xl font-bold text-primary-700">
                    {{ format_currency($this->categoryTotals->sum('total'), $this->currency) }}
                </p>
            </div>
        </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- ── OFFERINGS: Sunday vs Midweek table ─────────────────────────────── --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    @if ($this->offeringCategory && $this->offeringsData->isNotEmpty())
        @php
            $consolidatedSunday  = $this->offeringsData->sum('sunday_total');
            $consolidatedMidweek = $this->offeringsData->sum('midweek_total');
            $consolidatedTotal   = $this->offeringsData->sum('monthly_total');
        @endphp

        <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

            {{-- Section heading --}}
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="font-semibold text-gray-800">{{ $this->offeringCategory->name }}</h2>
                <p class="text-xs text-gray-400">Sunday Service vs Midweek &amp; Other Offerings · {{ $year }}</p>
            </div>

            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-widest text-gray-400">
                            Month
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-widest text-gray-400">
                            Sunday Service
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-widest text-gray-400">
                            Midweek &amp; Other Offerings
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-widest text-gray-400">
                            Total
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->offeringsData as $row)
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium text-gray-900">
                                {{ $row->month_name }}
                            </td>
                            <td class="px-6 py-4 text-gray-700">
                                {{ format_currency($row->sunday_total, $this->currency) }}
                            </td>
                            <td class="px-6 py-4 text-gray-700">
                                {{ format_currency($row->midweek_total, $this->currency) }}
                            </td>
                            <td class="px-6 py-4 font-bold text-gray-900">
                                {{ format_currency($row->monthly_total, $this->currency) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-gray-200 bg-gray-50">
                        <td class="px-6 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">
                            Consolidated Total
                        </td>
                        <td class="px-6 py-3 font-bold text-gray-800">
                            {{ format_currency($consolidatedSunday, $this->currency) }}
                        </td>
                        <td class="px-6 py-3 font-bold text-gray-800">
                            {{ format_currency($consolidatedMidweek, $this->currency) }}
                        </td>
                        <td class="px-6 py-3 font-bold text-gray-900">
                            {{ format_currency($consolidatedTotal, $this->currency) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @elseif ($this->offeringCategory && $this->offeringsData->isEmpty())
        <div class="mb-6 rounded-xl border border-gray-200 bg-white px-6 py-8 text-center shadow-sm">
            <p class="text-sm text-gray-400">No offering records found for the selected period.</p>
        </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- ── OTHER CATEGORIES: Per-member breakdown (accordion) ─────────────── --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    @php
        $otherCategories = $this->categories->filter(
            fn ($c) => ! in_array(strtolower($c->name), ['offering', 'offerings'])
        );
        // All 12 months always shown
        $allMonths = range(1, 12);
    @endphp

    @if ($otherCategories->isNotEmpty())
        <div class="space-y-3">
            <h2 class="font-semibold text-gray-800">Other Categories — Member Breakdown</h2>

            @foreach ($otherCategories as $category)
                @php $catTotal = $this->categoryTotals->get($category->id); @endphp

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

                    {{-- Accordion toggle --}}
                    <button
                        wire:click="setActiveCategory({{ $category->id }})"
                        class="flex w-full items-center justify-between px-6 py-4 text-left transition hover:bg-gray-50"
                    >
                        <div>
                            <p class="font-semibold text-gray-900">{{ $category->name }}</p>
                            @if ($catTotal)
                                <p class="text-xs text-gray-400">
                                    Total collected:
                                    <span class="font-medium text-gray-700">{{ format_currency($catTotal->total, $this->currency) }}</span>
                                </p>
                            @else
                                <p class="text-xs text-gray-400">No payments in this period</p>
                            @endif
                        </div>
                        <x-icon
                            name="{{ $activeCategory === $category->id ? 'chevron-up' : 'chevron-down' }}"
                            class="h-4 w-4 text-gray-400"
                        />
                    </button>

                    {{-- Expanded member table --}}
                    @if ($activeCategory === $category->id)
                        @php $memberData = $this->categoryMemberData; @endphp

                        <div class="border-t border-gray-100">
                            @if (empty($memberData))
                                <div class="px-6 py-8 text-center text-sm text-gray-400">
                                    No payments found for this category in the selected period.
                                </div>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead>
                                            <tr class="border-b border-gray-100 bg-white">
                                                {{-- Member column --}}
                                                <th class="w-44 px-6 py-3 text-left text-xs font-semibold uppercase tracking-widest text-gray-400">
                                                    Member
                                                </th>
                                                {{-- Always show all 12 months --}}
                                                @foreach ($allMonths as $mn)
                                                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-widest text-gray-400">
                                                        {{ \Carbon\Carbon::create()->month($mn)->format('M') }}
                                                    </th>
                                                @endforeach
                                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-widest text-gray-400">
                                                    Total
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php $grandTotal = 0; @endphp
                                            @foreach ($memberData as $memberName => $monthTotals)
                                                @php
                                                    $memberTotal = array_sum($monthTotals);
                                                    $grandTotal += $memberTotal;
                                                @endphp
                                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                                    <td class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-gray-700">
                                                        {{ $memberName }}
                                                    </td>
                                                    @foreach ($allMonths as $mn)
                                                        <td class="px-3 py-4 text-left text-gray-600">
                                                            {{ format_currency($monthTotals[$mn] ?? 0, $this->currency) }}
                                                        </td>
                                                    @endforeach
                                                    <td class="px-6 py-4 text-left font-bold text-gray-900">
                                                        {{ format_currency($memberTotal, $this->currency) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="border-t border-gray-200 bg-gray-50">
                                                <td class="px-6 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">
                                                    Consolidated Total
                                                </td>
                                                @foreach ($allMonths as $mn)
                                                    @php
                                                        $colSum = array_sum(array_map(fn ($m) => $m[$mn] ?? 0, $memberData));
                                                    @endphp
                                                    <td class="px-3 py-3 text-left font-bold text-gray-800">
                                                        {{ format_currency($colSum, $this->currency) }}
                                                    </td>
                                                @endforeach
                                                <td class="px-6 py-3 text-left font-bold text-gray-900">
                                                    {{ format_currency($grandTotal, $this->currency) }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @endif {{-- canViewReports --}}
<x-spinner/>
</div>
