<?php

use App\Exports\ExpensesExport;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Organization;
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

    // ── Filters ───────────────────────────────────────────────────────────────
    public string $year          = '';
    public string $month         = '';
    public string $filterDateFrom = '';
    public string $filterDateTo  = '';

    // ── Currency ──────────────────────────────────────────────────────────────
    public string $currency = 'ZMW';

    // ── Accordion ─────────────────────────────────────────────────────────────
    public ?int $activeCategory = null;

    public function mount(): void
    {
        $this->year = now()->format('Y');

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

        return Expense::where('organization_id', $org)
            ->selectRaw('YEAR(expense_date) as yr')
            ->groupBy('yr')
            ->orderByDesc('yr')
            ->pluck('yr')
            ->toArray();
    }

    /** Category totals with monthly sub-totals for the accordion */
    public function getCategoryBreakdownProperty(): Collection
    {
        $org = auth()->user()->myOrganization->organization_id;

        // Base query scope
        $scope = fn ($q) => $q
            ->where('expenses.organization_id', $org)
            ->whereYear('expense_date', $this->year)
            ->when($this->month,          fn ($q) => $q->whereMonth('expense_date', $this->month))
            ->when($this->filterDateFrom, fn ($q) => $q->whereDate('expense_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo,   fn ($q) => $q->whereDate('expense_date', '<=', $this->filterDateTo));

        // Category-level totals
        $categoryTotals = ExpenseCategory::where('organization_id', $org)
            ->withSum(['expenses' => function ($q) use ($org) {
                $q->where('expenses.organization_id', $org)
                  ->whereYear('expense_date', $this->year)
                  ->when($this->month,          fn ($q) => $q->whereMonth('expense_date', $this->month))
                  ->when($this->filterDateFrom, fn ($q) => $q->whereDate('expense_date', '>=', $this->filterDateFrom))
                  ->when($this->filterDateTo,   fn ($q) => $q->whereDate('expense_date', '<=', $this->filterDateTo));
            }], 'amount')
            ->withCount(['expenses' => function ($q) use ($org) {
                $q->where('expenses.organization_id', $org)
                  ->whereYear('expense_date', $this->year)
                  ->when($this->month,          fn ($q) => $q->whereMonth('expense_date', $this->month))
                  ->when($this->filterDateFrom, fn ($q) => $q->whereDate('expense_date', '>=', $this->filterDateFrom))
                  ->when($this->filterDateTo,   fn ($q) => $q->whereDate('expense_date', '<=', $this->filterDateTo));
            }])
            ->orderBy('name')
            ->get();

        // Monthly breakdown per category (loaded when accordion opens)
        if ($this->activeCategory) {
            $monthly = Expense::where('organization_id', $org)
                ->where('category_id', $this->activeCategory)
                ->whereYear('expense_date', $this->year)
                ->when($this->month,          fn ($q) => $q->whereMonth('expense_date', $this->month))
                ->when($this->filterDateFrom, fn ($q) => $q->whereDate('expense_date', '>=', $this->filterDateFrom))
                ->when($this->filterDateTo,   fn ($q) => $q->whereDate('expense_date', '<=', $this->filterDateTo))
                ->selectRaw("
                    MONTH(expense_date) as month_num,
                    MONTHNAME(expense_date) as month_name,
                    COUNT(*) as count,
                    SUM(amount) as total
                ")
                ->groupByRaw('MONTH(expense_date), MONTHNAME(expense_date)')
                ->orderByRaw('MONTH(expense_date)')
                ->get();

            $lineItems = Expense::where('organization_id', $org)
                ->where('category_id', $this->activeCategory)
                ->whereYear('expense_date', $this->year)
                ->when($this->month,          fn ($q) => $q->whereMonth('expense_date', $this->month))
                ->when($this->filterDateFrom, fn ($q) => $q->whereDate('expense_date', '>=', $this->filterDateFrom))
                ->when($this->filterDateTo,   fn ($q) => $q->whereDate('expense_date', '<=', $this->filterDateTo))
                ->orderBy('expense_date')
                ->get();

            // Attach to the matching category
            $categoryTotals->each(function ($cat) use ($monthly, $lineItems) {
                if ($cat->id === $this->activeCategory) {
                    $cat->monthly    = $monthly;
                    $cat->line_items = $lineItems;
                }
            });
        }

        return $categoryTotals;
    }

    public function getGrandTotalProperty(): float
    {
        $org = auth()->user()->myOrganization->organization_id;

        return Expense::where('organization_id', $org)
            ->whereYear('expense_date', $this->year)
            ->when($this->month,          fn ($q) => $q->whereMonth('expense_date', $this->month))
            ->when($this->filterDateFrom, fn ($q) => $q->whereDate('expense_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo,   fn ($q) => $q->whereDate('expense_date', '<=', $this->filterDateTo))
            ->sum('amount');
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function updatingYear(): void          { $this->activeCategory = null; }
    public function updatingMonth(): void         { $this->activeCategory = null; }
    public function updatingFilterDateFrom(): void { $this->activeCategory = null; }
    public function updatingFilterDateTo(): void   { $this->activeCategory = null; }

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
        $filename = 'expenses-report-' . $this->year . ($this->month ? '-' . $this->month : '') . '.xlsx';

        return Excel::download(
            new ExpensesExport($orgId, $this->year, $this->month, $this->filterDateFrom, $this->filterDateTo),
            $filename,
        );
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
            <h1 class="text-2xl font-bold text-gray-900">Expense Report</h1>
            <p class="mt-1 text-sm text-gray-500">Spending totals by category with line-item drill-down</p>
        </div>
        <x-button
            wire:click="exportExcel"
            wire:loading.attr="disabled"
            icon="arrow-down-tray"
            label="Export Excel"
            positive
            sm
        />
    </div>

    {{-- ── Filters ──────────────────────────────────────────────────────────── --}}
    <div class="mb-5 flex flex-wrap items-end gap-3">
        <x-select wire:model.live="year" class="w-28"
            :options="collect($this->availableYears)->map(fn ($yr) => ['value' => $yr, 'label' => $yr])->toArray()"
            option-value="value" option-label="label"
        />

        <x-select wire:model.live="month" class="w-36" placeholder="All months"
            :options="collect(range(1,12))->map(fn ($m) => ['value' => str_pad($m,2,'0',STR_PAD_LEFT), 'label' => \Carbon\Carbon::create()->month($m)->format('F')])->toArray()"
            option-value="value" option-label="label"
        />

        <div class="flex items-center gap-2">
            <x-datetime-picker wire:model.live="filterDateFrom" label="From" placeholder="From" without-time display-format="DD/MM/YYYY" class="w-36" />
            <x-datetime-picker wire:model.live="filterDateTo" label="To" placeholder="To" without-time display-format="DD/MM/YYYY" class="w-36" />
        </div>

        @if ($month || $filterDateFrom || $filterDateTo)
            <x-button
                wire:click="$set('month',''); $set('filterDateFrom',''); $set('filterDateTo','')"
                label="Clear"
                icon="x-mark"
                flat
                sm
            />
        @endif
    </div>

    {{-- ── Grand Total Banner ───────────────────────────────────────────────── --}}
    <div class="mb-6 flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50 px-6 py-5 shadow-sm">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-rose-400">Grand Total Expenses</p>
            <p class="mt-1 text-3xl font-bold text-rose-700">{{ format_currency($this->grandTotal, $this->currency) }}</p>
        </div>
        <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-rose-100">
            <x-icon name="banknotes" class="h-7 w-7 text-rose-500" />
        </div>
    </div>

    {{-- ── Category Summary Grid ────────────────────────────────────────────── --}}
    @php
        $breakdown = $this->categoryBreakdown->filter(fn ($c) => $c->expenses_sum_amount > 0);
    @endphp

    @if ($breakdown->isNotEmpty())
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($breakdown as $cat)
                @php $pct = $this->grandTotal > 0 ? ($cat->expenses_sum_amount / $this->grandTotal) * 100 : 0; @endphp
                <button
                    wire:click="setActiveCategory({{ $cat->id }})"
                    class="rounded-xl border p-4 text-left shadow-sm transition
                        {{ $activeCategory === $cat->id
                            ? 'border-primary-300 bg-primary-50 ring-2 ring-primary-200'
                            : 'border-gray-200 bg-white hover:border-primary-200 hover:bg-primary-50' }}"
                >
                    <p class="truncate text-xs font-medium uppercase tracking-wide text-gray-400">{{ $cat->name }}</p>
                    <p class="mt-1 text-lg font-bold text-gray-900">{{ format_currency($cat->expenses_sum_amount, $this->currency) }}</p>
                    <div class="mt-2 flex items-center gap-2">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-200">
                            <div class="h-1.5 rounded-full bg-rose-400" style="width: {{ $pct }}%"></div>
                        </div>
                        <span class="text-xs text-gray-400">{{ number_format($pct, 1) }}%</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-400">{{ $cat->expenses_count }} expense(s)</p>
                </button>
            @endforeach
        </div>
    @endif

    {{-- ── Category Accordion Detail ────────────────────────────────────────── --}}
    <div class="space-y-4">
        @forelse ($this->categoryBreakdown as $cat)
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

                {{-- Accordion header --}}
                <button
                    wire:click="setActiveCategory({{ $cat->id }})"
                    class="flex w-full items-center justify-between px-6 py-4 text-left transition hover:bg-gray-50"
                >
                    <div class="flex items-center gap-4">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-rose-50 text-rose-500">
                            <x-icon name="folder" class="h-4 w-4" />
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">{{ $cat->name }}</p>
                            <p class="text-xs text-gray-400">{{ $cat->expenses_count }} expense(s)</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="text-right">
                            <p class="font-bold text-gray-900">{{ format_currency($cat->expenses_sum_amount ?? 0, $this->currency) }}</p>
                            @if ($this->grandTotal > 0 && $cat->expenses_sum_amount > 0)
                                @php $pct = ($cat->expenses_sum_amount / $this->grandTotal) * 100; @endphp
                                <p class="text-xs text-gray-400">{{ number_format($pct, 1) }}% of total</p>
                            @endif
                        </div>
                        <x-icon
                            name="{{ $activeCategory === $cat->id ? 'chevron-up' : 'chevron-down' }}"
                            class="h-4 w-4 text-gray-400"
                        />
                    </div>
                </button>

                {{-- Accordion body --}}
                @if ($activeCategory === $cat->id)
                    <div class="border-t border-gray-100">

                        {{-- Monthly sub-totals --}}
                        @if (isset($cat->monthly) && $cat->monthly->isNotEmpty())
                            <div class="border-b border-gray-100 px-6 py-4">
                                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Monthly Breakdown</p>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    @foreach ($cat->monthly as $mo)
                                        <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                                            <p class="text-xs text-gray-400">{{ $mo->month_name }}</p>
                                            <p class="mt-0.5 font-semibold text-gray-800">{{ format_currency($mo->total, $this->currency) }}</p>
                                            <p class="text-xs text-gray-400">{{ $mo->count }} item(s)</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Line items --}}
                        @if (isset($cat->line_items) && $cat->line_items->isNotEmpty())
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-100 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Title</th>
                                            <th class="px-6 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Description</th>
                                            <th class="px-6 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Date</th>
                                            <th class="px-6 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Receipt</th>
                                            <th class="px-6 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-400">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50 bg-white">
                                        @foreach ($cat->line_items as $item)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-3 font-medium text-gray-900">{{ $item->title }}</td>
                                                <td class="px-6 py-3 max-w-xs truncate text-gray-500 text-xs">
                                                    {{ $item->description ?? '—' }}
                                                </td>
                                                <td class="px-6 py-3 text-gray-500">
                                                    {{ $item->expense_date->format('M d, Y') }}
                                                </td>
                                                <td class="px-6 py-3">
                                                    @if ($item->image_url)
                                                        <a href="{{ Storage::url($item->image_url) }}" target="_blank"
                                                           class="inline-flex items-center gap-1 rounded border border-gray-200 px-2 py-0.5 text-xs text-gray-500 hover:bg-gray-50">
                                                            <x-icon name="paper-clip" class="h-3 w-3" /> View
                                                        </a>
                                                    @else
                                                        <span class="text-xs text-gray-300">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-3 text-right font-semibold text-gray-900">
                                                    {{ format_currency($item->amount, $this->currency) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="border-t border-gray-200 bg-gray-50">
                                        <tr>
                                            <td colspan="4" class="px-6 py-3 text-xs font-bold uppercase text-gray-500">Category Total</td>
                                            <td class="px-6 py-3 text-right font-bold text-rose-600">
                                                {{ format_currency($cat->expenses_sum_amount ?? 0, $this->currency) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <div class="px-6 py-8 text-center text-sm text-gray-400">
                                No expenses in this category for the selected period.
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 bg-white px-6 py-16 text-center shadow-sm">
                <div class="flex flex-col items-center gap-2 text-gray-400">
                    <x-icon name="receipt-percent" class="h-10 w-10 opacity-40" />
                    <p class="text-sm font-medium">No expense data for the selected period</p>
                </div>
            </div>
        @endforelse

        {{-- ── Overall Total Row ──────────────────────────────────────────── --}}
        @if ($this->grandTotal > 0)
            <div class="flex items-center justify-between rounded-xl border-2 border-gray-300 bg-white px-6 py-4 shadow-sm">
                <p class="font-bold text-gray-700 uppercase text-sm tracking-wide">Overall Total</p>
                <p class="text-2xl font-bold text-gray-900">{{ format_currency($this->grandTotal, $this->currency) }}</p>
            </div>
        @endif
    </div>

    @endif {{-- canViewReports --}}
<x-spinner/>
</div>
