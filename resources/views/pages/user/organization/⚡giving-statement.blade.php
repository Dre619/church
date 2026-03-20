<?php

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Payments;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $currency  = 'ZMW';
    public int    $year;
    public string $search    = '';

    public function mount(): void
    {
        $this->year = (int) now()->format('Y');
        $orgId = auth()->user()->myOrganization->organization_id;
        $this->currency = Organization::find($orgId)?->currency ?? 'ZMW';
    }

    public function getOrgIdProperty(): int
    {
        return auth()->user()->myOrganization->organization_id;
    }

    public function getMembersProperty()
    {
        return User::whereHas('myOrganization', fn ($q) => $q->where('organization_id', $this->orgId))
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->withSum(['payments as total_given' => fn ($q) =>
                $q->where('organization_id', $this->orgId)->whereYear('donation_date', $this->year)
            ], 'amount')
            ->orderBy('name')
            ->paginate(20);
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingYear(): void   { $this->resetPage(); }

    public function getAvailableYearsProperty(): array
    {
        return Payments::where('organization_id', $this->orgId)
            ->selectRaw('YEAR(donation_date) as yr')
            ->groupBy('yr')->orderByDesc('yr')
            ->pluck('yr')->toArray() ?: [(int) now()->year];
    }
};
?>

<div class="min-h-screen bg-gray-50 p-6">

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Annual Giving Statements</h1>
            <p class="mt-1 text-sm text-gray-500">Generate PDF or Excel statements of each member's contributions for the year</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <x-select wire:model.live="year" class="w-28"
                :options="collect($this->availableYears)->map(fn ($yr) => ['value' => $yr, 'label' => $yr])->toArray()"
                option-value="value" option-label="label"
            />
            <a href="{{ route('giving.statement.excel.all', $year) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-semibold transition-colors border border-emerald-200">
                <x-icon name="table-cells" class="w-3.5 h-3.5" />
                Export All to Excel
            </a>
        </div>
    </div>

    <div class="mb-4">
        <x-input wire:model.live.debounce.300ms="search" placeholder="Search member name…" icon="magnifying-glass" class="max-w-xs" />
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Member</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Total Given {{ $year }}</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Statement</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 bg-white">
                @forelse ($this->members as $member)
                    <tr class="hover:bg-gray-50" wire:key="gs-{{ $member->id }}">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700">
                                    {{ strtoupper(substr($member->name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ $member->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $member->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right font-semibold {{ $member->total_given > 0 ? 'text-green-700' : 'text-gray-400' }}">
                            {{ format_currency($member->total_given ?? 0, $currency) }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('giving.statement.download', [$member->id, $year]) }}"
                                   target="_blank"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold transition-colors">
                                    <x-icon name="document-arrow-down" class="w-3.5 h-3.5" />
                                    PDF
                                </a>
                                <a href="{{ route('giving.statement.excel', [$member->id, $year]) }}"
                                   target="_blank"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-semibold transition-colors">
                                    <x-icon name="table-cells" class="w-3.5 h-3.5" />
                                    Excel
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <x-icon name="document-text" class="h-10 w-10 opacity-40" />
                                <p class="text-sm">No members found.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($this->members->hasPages())
            <div class="border-t border-gray-100 px-6 py-4">
                {{ $this->members->links() }}
            </div>
        @endif
    </div>
    <x-spinner/>
</div>
