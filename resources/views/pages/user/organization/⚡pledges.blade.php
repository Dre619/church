<?php

use App\Models\Organization;
use App\Models\Pledge;
use App\Models\Projects;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new class extends Component
{
    use WithPagination, WireUiActions;

    // ── Modals ────────────────────────────────────────────────────────────────
    public bool $modalOpen         = false;
    public bool $confirmDeleteOpen = false;
    public bool $viewModalOpen     = false;

    // ── Form ──────────────────────────────────────────────────────────────────
    public ?int    $editingId        = null;
    public ?int    $user_id          = null;
    public ?int    $project_id       = null;
    public string  $amount           = '';
    public string  $pledge_date      = '';
    public string  $deadline         = '';
    public string  $status           = 'pending';

    // ── View ──────────────────────────────────────────────────────────────────
    public ?Pledge $viewingPledge = null;

    // ── Filters ───────────────────────────────────────────────────────────────
    public string $search        = '';
    public string $filterStatus  = '';
    public string $filterProject = '';

    // ── Delete ────────────────────────────────────────────────────────────────
    public ?int $deletingId = null;

    // ── Currency ──────────────────────────────────────────────────────────────
    public string $currency = 'ZMW';

    // ── Lookups ───────────────────────────────────────────────────────────────
    public array $memberOptions  = [];
    public array $projectOptions = [];

    public array $statusOptions = [
        'pending'   => 'Pending',
        'partially_fulfilled'   => 'Partial',
        'fulfilled' => 'Fulfilled',
        'cancelled' => 'Cancelled',
    ];

    // ─────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $organization_id = auth()->user()->myOrganization->organization_id;

        $this->currency = Organization::find($organization_id)?->currency ?? 'ZMW';

        $this->memberOptions = User::whereHas('myOrganization', fn ($q) =>
                $q->where('organization_id', $organization_id)
            )
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => ['value' => $u->id, 'label' => "{$u->name} ({$u->email})"])
            ->toArray();

        $this->projectOptions = Projects::where('organization_id', $organization_id)
            ->orderBy('project_title')
            ->get()
            ->map(fn ($p) => ['value' => $p->id, 'label' => $p->project_title])
            ->toArray();

        $this->pledge_date = now()->format('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'user_id'     => 'required|exists:users,id',
            'project_id'  => 'required|exists:projects,id',
            'amount'      => 'required|numeric|min:0.01',
            'pledge_date' => 'required|date',
            'deadline'    => 'nullable|date|after_or_equal:pledge_date',
            'status'      => 'required|in:pending,partial,fulfilled,cancelled',
        ];
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getPledgesProperty()
    {
        $organization_id = auth()->user()->myOrganization->organization_id;

        return Pledge::with(['user', 'project'])
            ->where('organization_id', $organization_id)
            ->when($this->search, fn ($q) =>
                $q->whereHas('user', fn ($q) =>
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%")
                )
            )
            ->when($this->filterStatus, fn ($q) =>
                $q->where('status', $this->filterStatus)
            )
            ->when($this->filterProject, fn ($q) =>
                $q->where('project_id', $this->filterProject)
            )
            ->latest('pledge_date')
            ->paginate(10);
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function updatingSearch(): void        { $this->resetPage(); }
    public function updatingFilterStatus(): void  { $this->resetPage(); }
    public function updatingFilterProject(): void { $this->resetPage(); }

    // ── View ──────────────────────────────────────────────────────────────────

    public function openView(int $id): void
    {
        $this->viewingPledge = Pledge::with(['user', 'project', 'donations.category'])->findOrFail($id);
        $this->viewModalOpen = true;
    }

    // ── Create / Edit ─────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetForm();
        $this->modalOpen = true;
    }

    public function openEdit(int $id): void
    {
        $this->resetForm();
        $pledge             = Pledge::findOrFail($id);
        $this->editingId    = $pledge->id;
        $this->user_id      = $pledge->user_id;
        $this->project_id   = $pledge->project_id;
        $this->amount       = $pledge->amount;
        $this->pledge_date  = $pledge->pledge_date->format('Y-m-d');
        $this->deadline     = $pledge->deadline?->format('Y-m-d') ?? '';
        $this->status       = $pledge->status;
        $this->modalOpen    = true;
    }

    public function save(): void
    {
        $this->validate();

        $organization_id = auth()->user()->myOrganization->organization_id;

        $data = [
            'organization_id' => $organization_id,
            'user_id'         => $this->user_id,
            'project_id'      => $this->project_id,
            'amount'          => $this->amount,
            'pledge_date'     => $this->pledge_date,
            'deadline'        => $this->deadline ?: null,
            'status'          => $this->status,
        ];

        if ($this->editingId) {
            Pledge::findOrFail($this->editingId)->update($data);
            $this->notification()->success('Pledge updated', 'The pledge has been updated successfully.');
        } else {
            Pledge::create($data);
            $this->notification()->success('Pledge created', 'New pledge has been recorded.');
        }

        $this->modalOpen = false;
        $this->resetForm();
        $this->resetPage();
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $this->deletingId      = $id;
        $this->confirmDeleteOpen = true;
    }

    public function delete(): void
    {
        if (! $this->deletingId) return;

        Pledge::findOrFail($this->deletingId)->delete();

        $this->notification()->success('Pledge deleted', 'The pledge record has been removed.');
        $this->confirmDeleteOpen = false;
        $this->deletingId        = null;
        $this->resetPage();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resetForm(): void
    {
        $this->editingId   = null;
        $this->user_id     = null;
        $this->project_id  = null;
        $this->amount      = '';
        $this->pledge_date = now()->format('Y-m-d');
        $this->deadline    = '';
        $this->status      = 'pending';
        $this->resetErrorBag();
    }
};
?>

<div class="min-h-screen bg-gray-50 p-6">

    {{-- ── Page Header ──────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Pledges</h1>
            <p class="mt-1 text-sm text-gray-500">Track member pledges and fulfillment progress</p>
        </div>
        <x-button wire:click="openCreate" label="New Pledge" icon="plus" primary />
    </div>

    {{-- ── Filters ──────────────────────────────────────────────────────────── --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-input
            wire:model.live.debounce.300ms="search"
            placeholder="Search by member name…"
            icon="magnifying-glass"
            class="max-w-xs"
        />
        <x-select wire:model.live="filterProject" class="max-w-[200px]" placeholder="All projects"
            :options="$projectOptions" option-value="value" option-label="label"
        />
        <x-select wire:model.live="filterStatus" class="max-w-[150px]" placeholder="All statuses"
            :options="collect($statusOptions)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values()->toArray()"
            option-value="value" option-label="label"
        />
    </div>

    {{-- ── Table ────────────────────────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Member</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Project</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Progress</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Deadline</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($this->pledges as $pledge)
                    @php
                        $pct = $pledge->amount > 0 ? min(100, ($pledge->donations->sum('amount') / $pledge->amount) * 100) : 0;
                        $statusColors = [
                            'pending'   => 'bg-yellow-100 text-yellow-700',
                            'partial'   => 'bg-blue-100 text-blue-700',
                            'fulfilled' => 'bg-green-100 text-green-700',
                            'cancelled' => 'bg-red-100 text-red-700',
                        ];
                        $barColors = [
                            'pending'   => 'bg-yellow-400',
                            'partially_fulfilled'   => 'bg-blue-500',
                            'fulfilled' => 'bg-green-500',
                            'cancelled' => 'bg-red-400',
                        ];
                    @endphp
                    <tr wire:key="pledge-{{ $pledge->id }}" class="transition hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700">
                                    {{ $pledge->user?->initials() }}
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ $pledge->user?->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $pledge->pledge_date->format('M d, Y') }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-gray-700">{{ $pledge->project?->project_title }}</td>
                        <td class="px-6 py-4">
                            <p class="font-semibold text-gray-900">{{ format_currency($pledge->amount, $this->currency) }}</p>
                            <p class="text-xs text-gray-400">Paid: {{ format_currency($pledge->donations->sum('amount'), $this->currency) }}</p>
                        </td>
                        <td class="px-6 py-4 w-32">
                            <div class="flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200">
                                    <div class="h-2 rounded-full {{ $barColors[$pledge->status] ?? 'bg-gray-400' }} transition-all"
                                         style="width: {{ $pct }}%"></div>
                                </div>
                                <span class="text-xs text-gray-500">{{ number_format($pct, 0) }}%</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize {{ $statusColors[$pledge->status] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $statusOptions[$pledge->status] ?? $pledge->status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500">
                            {{ $pledge->deadline ? $pledge->deadline->format('M d, Y') : '—' }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <x-mini-button wire:click="openView({{ $pledge->id }})"      icon="eye"    flat secondary sm />
                                <x-mini-button wire:click="openEdit({{ $pledge->id }})"      icon="pencil" flat secondary sm />
                                <x-mini-button wire:click="confirmDelete({{ $pledge->id }})" icon="trash"  flat negative  sm />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <x-icon name="clipboard-document-list" class="h-10 w-10 opacity-40" />
                                <p class="text-sm font-medium">No pledges found</p>
                                @if ($search || $filterStatus || $filterProject)
                                    <p class="text-xs">Try adjusting your filters</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($this->pledges->hasPages())
            <div class="border-t border-gray-100 px-6 py-4">
                {{ $this->pledges->links() }}
            </div>
        @endif
    </div>

    {{-- ── View Pledge Modal ────────────────────────────────────────────────── --}}
    <x-modal wire:model="viewModalOpen" max-width="2xl">
        @if ($viewingPledge)
            @php
                $pct = $viewingPledge->amount > 0
                    ? min(100, ($viewingPledge->fulfilled_amount / $viewingPledge->amount) * 100)
                    : 0;
            @endphp
            <x-card title="Pledge Details" class="relative">
                {{-- Summary --}}
                <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 text-center">
                        <p class="text-lg font-bold text-gray-900">{{ format_currency($viewingPledge->amount, $this->currency) }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">Pledged</p>
                    </div>
                    <div class="rounded-xl border border-green-100 bg-green-50 p-4 text-center">
                        <p class="text-lg font-bold text-green-700">{{ format_currency($viewingPledge->fulfilled_amount, $this->currency) }}</p>
                        <p class="mt-0.5 text-xs text-green-600">Paid</p>
                    </div>
                    <div class="rounded-xl border border-orange-100 bg-orange-50 p-4 text-center">
                        <p class="text-lg font-bold text-orange-700">{{ format_currency($viewingPledge->balance, $this->currency) }}</p>
                        <p class="mt-0.5 text-xs text-orange-600">Balance</p>
                    </div>
                    <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-center">
                        <p class="text-lg font-bold text-blue-700">{{ number_format($pct, 0) }}%</p>
                        <p class="mt-0.5 text-xs text-blue-600">Complete</p>
                    </div>
                </div>

                {{-- Progress bar --}}
                <div class="mb-6 h-3 overflow-hidden rounded-full bg-gray-200">
                    <div class="h-3 rounded-full bg-primary-500 transition-all" style="width: {{ $pct }}%"></div>
                </div>

                {{-- Info --}}
                <div class="mb-6 grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-400">Member</p>
                        <p class="mt-1 text-gray-900">{{ $viewingPledge->user?->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-400">Project</p>
                        <p class="mt-1 text-gray-900">{{ $viewingPledge->project?->project_title }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-400">Pledge Date</p>
                        <p class="mt-1 text-gray-900">{{ $viewingPledge->pledge_date->format('M d, Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-400">Deadline</p>
                        <p class="mt-1 text-gray-900">{{ $viewingPledge->deadline?->format('M d, Y') ?? '—' }}</p>
                    </div>
                </div>

                {{-- Payments on this pledge --}}
                <div>
                    <p class="mb-2 text-sm font-semibold text-gray-700">Payment History</p>
                    @if ($viewingPledge->donations->isEmpty())
                        <p class="text-sm text-gray-400">No payments recorded yet.</p>
                    @else
                        <div class="overflow-hidden rounded-lg border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-100 text-xs">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-500">Date</th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-500">Category</th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-500">Method</th>
                                        <th class="px-4 py-2 text-right font-semibold text-gray-500">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 bg-white">
                                    @foreach ($viewingPledge->donations as $donation)
                                        <tr>
                                            <td class="px-4 py-2 text-gray-500">
                                                {{ \Carbon\Carbon::parse($donation->donation_date)->format('M d, Y') }}
                                            </td>
                                            <td class="px-4 py-2 text-gray-700">{{ $donation->category?->name ?? '—' }}</td>
                                            <td class="px-4 py-2 capitalize text-gray-600">{{ str_replace('_', ' ', $donation->payment_method) }}</td>
                                            <td class="px-4 py-2 text-right font-semibold text-gray-900">{{ format_currency($donation->amount, $this->currency) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <x-slot name="footer">
                    <div class="flex justify-end gap-3">
                        <x-button wire:click="openEdit({{ $viewingPledge->id }})" label="Edit Pledge" secondary />
                        <x-button wire:click="$set('viewModalOpen', false)" label="Close" flat />
                    </div>
                </x-slot>
            </x-card>
        @endif
    </x-modal>

    {{-- ── Create / Edit Modal ─────────────────────────────────────────────── --}}
    <x-modal wire:model="modalOpen" max-width="lg">
        <x-card :title="$editingId ? 'Edit Pledge' : 'New Pledge'" class="relative">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                <div class="sm:col-span-2">
                    <x-select
                        wire:model="user_id"
                        label="Member"
                        placeholder="Select member"
                        :options="$memberOptions"
                        option-value="value"
                        option-label="label"
                        searchable
                    />
                </div>

                <div class="sm:col-span-2">
                    <x-select
                        wire:model="project_id"
                        label="Project"
                        placeholder="Select project"
                        :options="$projectOptions"
                        option-value="value"
                        option-label="label"
                        searchable
                    />
                </div>

                <x-input
                    wire:model="amount"
                    label="Pledge Amount"
                    placeholder="0.00"
                    icon="currency-dollar"
                    type="number"
                    min="0"
                    step="0.01"
                />

                <x-select wire:model="status" label="Status"
                    :options="collect($statusOptions)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values()->toArray()"
                    option-value="value" option-label="label"
                />

                <x-datetime-picker wire:model="pledge_date" label="Pledge Date" placeholder="Pledge Date" without-time display-format="DD/MM/YYYY" />
                <x-datetime-picker wire:model="deadline" label="Deadline (optional)" placeholder="Deadline (optional)" without-time display-format="DD/MM/YYYY" />
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button wire:click="$set('modalOpen', false)" label="Cancel" flat />
                    <x-button
                        wire:click="save"
                        wire:loading.attr="disabled"
                        label="{{ $editingId ? 'Update Pledge' : 'Save Pledge' }}"
                        primary
                        spinner="save"
                    />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

    {{-- ── Confirm Delete ───────────────────────────────────────────────────── --}}
    <x-modal wire:model="confirmDeleteOpen" max-width="sm">
        <x-card title="Delete Pledge" class="relative">
            <p class="text-sm text-gray-600">
                Are you sure you want to delete this pledge? Associated payment links will be removed.
            </p>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button wire:click="$set('confirmDeleteOpen', false)" label="Cancel" flat />
                    <x-button wire:click="delete" wire:loading.attr="disabled" label="Delete" negative spinner="delete" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>
<x-spinner/>
</div>
