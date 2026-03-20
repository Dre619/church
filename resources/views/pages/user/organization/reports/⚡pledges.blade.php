<?php

use App\Exports\PledgesExport;
use App\Models\Organization;
use App\Models\Pledge;
use App\Models\Projects;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use WireUi\Traits\WireUiActions;
use Illuminate\Support\Collection;

new class extends Component
{
    use WithPagination, WireUiActions;

    // ── Plan gates ────────────────────────────────────────────────────────────
    public bool $canViewReports = true;
    public bool $canExport      = true;

    // ── Filters ───────────────────────────────────────────────────────────────
    public string $year          = '';
    public ?int   $filterMember  = null;
    public ?int   $filterProject = null;
    public string $filterStatus  = '';

    // ── Currency ──────────────────────────────────────────────────────────────
    public string $currency = 'ZMW';

    // ── Lookups ───────────────────────────────────────────────────────────────
    public array $memberOptions  = [];
    public array $projectOptions = [];

    public array $statusOptions = [
        'pending'   => 'Pending',
        'partial'   => 'Partial',
        'fulfilled' => 'Fulfilled',
        'cancelled' => 'Cancelled',
    ];

    public function mount(): void
    {
        $this->year = now()->format('Y');

        $org = auth()->user()->myOrganization->organization_id;

        $this->currency = Organization::find($org)?->currency ?? 'ZMW';

        $this->memberOptions = User::whereHas('myOrganization', fn ($q) =>
                $q->where('organization_id', $org)
            )
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])
            ->toArray();

        $this->projectOptions = Projects::where('organization_id', $org)
            ->orderBy('project_title')
            ->get()
            ->map(fn ($p) => ['value' => $p->id, 'label' => $p->project_title])
            ->toArray();

        $plan = active_plan();
        $this->canViewReports = $plan?->can_view_reports ?? true;
        $this->canExport      = $plan?->can_export ?? true;
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getAvailableYearsProperty(): array
    {
        $org = auth()->user()->myOrganization->organization_id;

        return Pledge::where('organization_id', $org)
            ->selectRaw('YEAR(pledge_date) as yr')
            ->groupBy('yr')
            ->orderByDesc('yr')
            ->pluck('yr')
            ->toArray();
    }

    public function getPledgesProperty()
    {
        $org = auth()->user()->myOrganization->organization_id;

        return Pledge::with(['user', 'project', 'donations'])
            ->where('organization_id', $org)
            ->whereYear('pledge_date', $this->year)
            ->when($this->filterMember,  fn ($q) => $q->where('user_id', $this->filterMember))
            ->when($this->filterProject, fn ($q) => $q->where('project_id', $this->filterProject))
            ->when($this->filterStatus,  fn ($q) => $q->where('status', $this->filterStatus))
            ->orderBy('pledge_date')
            ->paginate(20);
    }

    public function getSummaryProperty(): array
    {
        $org = auth()->user()->myOrganization->organization_id;

        $rows = Pledge::where('organization_id', $org)
            ->whereYear('pledge_date', $this->year)
            ->when($this->filterMember,  fn ($q) => $q->where('user_id', $this->filterMember))
            ->when($this->filterProject, fn ($q) => $q->where('project_id', $this->filterProject))
            ->when($this->filterStatus,  fn ($q) => $q->where('status', $this->filterStatus))
            ->selectRaw('
                COUNT(*) as total_pledges,
                SUM(amount) as total_pledged,
                SUM(fulfilled_amount) as total_paid
            ')
            ->first();

        return [
            'total_pledges'  => $rows->total_pledges ?? 0,
            'total_pledged'  => $rows->total_pledged ?? 0,
            'total_paid'     => $rows->total_paid    ?? 0,
            'total_balance'  => max(0, ($rows->total_pledged ?? 0) - ($rows->total_paid ?? 0)),
        ];
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function updatingFilterMember(): void  { $this->resetPage(); }
    public function updatingFilterProject(): void { $this->resetPage(); }
    public function updatingFilterStatus(): void  { $this->resetPage(); }
    public function updatingYear(): void          { $this->resetPage(); }

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
        $filename = 'pledges-report-' . $this->year . '.xlsx';

        return Excel::download(
            new PledgesExport($orgId, $this->year, $this->filterMember, $this->filterProject, $this->filterStatus),
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
            <h1 class="text-2xl font-bold text-gray-900">Pledge Report</h1>
            <p class="mt-1 text-sm text-gray-500">Pledged amounts, payments made, and outstanding balances</p>
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

        <div class="min-w-[200px]">
            <x-select
                wire:model.live="filterMember"
                placeholder="All members"
                :options="$memberOptions"
                option-value="value"
                option-label="label"
                searchable
                clearable
            />
        </div>

        <div class="min-w-[200px]">
            <x-select
                wire:model.live="filterProject"
                placeholder="All projects"
                :options="$projectOptions"
                option-value="value"
                option-label="label"
                searchable
                clearable
            />
        </div>

        <x-select wire:model.live="filterStatus" class="w-36" placeholder="All statuses"
            :options="collect($statusOptions)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values()->toArray()"
            option-value="value" option-label="label"
        />
    </div>

    {{-- ── Summary Cards ────────────────────────────────────────────────────── --}}
    @php $summary = $this->summary; @endphp
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Total Pledges</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $summary['total_pledges'] }}</p>
        </div>
        <div class="rounded-xl border border-blue-100 bg-blue-50 p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-blue-400">Total Pledged</p>
            <p class="mt-1 text-2xl font-bold text-blue-700">{{ format_currency($summary['total_pledged'], $this->currency) }}</p>
        </div>
        <div class="rounded-xl border border-green-100 bg-green-50 p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-green-400">Total Paid</p>
            <p class="mt-1 text-2xl font-bold text-green-700">{{ format_currency($summary['total_paid'], $this->currency) }}</p>
        </div>
        <div class="rounded-xl border border-orange-100 bg-orange-50 p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-orange-400">Outstanding Balance</p>
            <p class="mt-1 text-2xl font-bold text-orange-700">{{ format_currency($summary['total_balance'], $this->currency) }}</p>
        </div>
    </div>

    {{-- ── Pledge Table ─────────────────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Member</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Project</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Pledge Date</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Amount Pledged</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Amount Paid</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Balance</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Progress</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Deadline</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @php
                    $statusColors = [
                        'pending'   => 'bg-yellow-100 text-yellow-700',
                        'partial'   => 'bg-blue-100 text-blue-700',
                        'fulfilled' => 'bg-green-100 text-green-700',
                        'cancelled' => 'bg-red-100 text-red-700',
                    ];
                @endphp

                @forelse ($this->pledges as $pledge)
                    @php
                        $paid    = $pledge->fulfilled_amount ?? 0;
                        $balance = max(0, $pledge->amount - $paid);
                        $pct     = $pledge->amount > 0 ? min(100, ($paid / $pledge->amount) * 100) : 0;
                    @endphp
                    <tr wire:key="pr-{{ $pledge->id }}" class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">
                            {{ $pledge->user?->name ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-gray-600">
                            {{ $pledge->project?->project_title ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-gray-500">
                            {{ $pledge->pledge_date->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 text-right font-semibold text-gray-900">
                            {{ format_currency($pledge->amount, $this->currency) }}
                        </td>
                        <td class="px-6 py-4 text-right font-semibold text-green-700">
                            {{ format_currency($paid, $this->currency) }}
                        </td>
                        <td class="px-6 py-4 text-right font-semibold {{ $balance > 0 ? 'text-orange-600' : 'text-gray-400' }}">
                            {{ format_currency($balance, $this->currency) }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200">
                                    <div class="h-2 rounded-full transition-all
                                        {{ $pct >= 100 ? 'bg-green-500' : ($pct >= 50 ? 'bg-blue-500' : 'bg-yellow-400') }}"
                                         style="width: {{ $pct }}%"></div>
                                </div>
                                <span class="w-9 text-right text-xs text-gray-500">{{ number_format($pct, 0) }}%</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize {{ $statusColors[$pledge->status] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $statusOptions[$pledge->status] ?? $pledge->status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500 text-xs">
                            {{ $pledge->deadline ? $pledge->deadline->format('M d, Y') : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <x-icon name="clipboard-document-list" class="h-10 w-10 opacity-40" />
                                <p class="text-sm font-medium">No pledges found for the selected filters</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>

            {{-- Totals footer --}}
            @if ($this->pledges->isNotEmpty())
                <tfoot class="border-t-2 border-gray-200 bg-gray-50">
                    <tr>
                        <td colspan="3" class="px-6 py-3 text-xs font-bold uppercase text-gray-500">
                            Page Total ({{ $this->pledges->count() }} records)
                        </td>
                        <td class="px-6 py-3 text-right font-bold text-gray-900">
                            {{ format_currency($this->pledges->sum('amount'), $this->currency) }}
                        </td>
                        <td class="px-6 py-3 text-right font-bold text-green-700">
                            {{ format_currency($this->pledges->sum('fulfilled_amount'), $this->currency) }}
                        </td>
                        <td class="px-6 py-3 text-right font-bold text-orange-600">
                            {{ format_currency($this->pledges->sum(fn ($p) => max(0, $p->amount - $p->fulfilled_amount)), $this->currency) }}
                        </td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            @endif
        </table>

        @if ($this->pledges->hasPages())
            <div class="border-t border-gray-100 px-6 py-4">
                {{ $this->pledges->links() }}
            </div>
        @endif
    </div>

    @endif {{-- canViewReports --}}
<x-spinner/>
</div>
