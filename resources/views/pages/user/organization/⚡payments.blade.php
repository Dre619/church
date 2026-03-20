<?php

use App\Models\Organization;
use App\Models\Payments;
use App\Models\PaymentCategory;
use App\Models\Pledge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new class extends Component
{
    use WithPagination, WireUiActions;

    // ── Modals ────────────────────────────────────────────────────────────────
    public bool $modalOpen         = false;
    public bool $confirmDeleteOpen = false;

    // ── Form ──────────────────────────────────────────────────────────────────
    public ?int    $editingId       = null;
    public ?int    $user_id         = null;
    public ?int    $category_id     = null;
    public ?int    $pledge_id       = null;
    public string  $name            = '';
    public string  $amount          = '';
    public string  $other           = '';
    public string  $payment_method  = '';
    public string  $transaction_id  = '';
    public string  $donation_date   = '';

    // ── Filters ───────────────────────────────────────────────────────────────
    public string $search          = '';
    public string $filterCategory  = '';
    public string $filterMethod    = '';

    // ── Delete ────────────────────────────────────────────────────────────────
    public ?int $deletingId = null;

    // ── Currency ──────────────────────────────────────────────────────────────
    public string $currency = 'ZMW';

    // ── Lookups ───────────────────────────────────────────────────────────────
    public array $categoryOptions = [];
    public array $memberOptions   = [];
    public array $pledgeOptions   = [];

    public array $paymentMethods = [
        'cash'          => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'mobile_money'  => 'Mobile Money',
        'cheque'        => 'Cheque',
        'card'          => 'Card',
        'other'         => 'Other',
    ];

    // ─────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $organization_id = auth()->user()->myOrganization->organization_id;

        $this->currency = Organization::find($organization_id)?->currency ?? 'ZMW';

        $this->categoryOptions = PaymentCategory::where('organization_id', $organization_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])
            ->toArray();

        $this->memberOptions = User::whereHas('myOrganization', fn ($q) =>
                $q->where('organization_id', $organization_id)
            )
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => ['value' => $u->id, 'label' => "{$u->name} ({$u->email})"])
            ->toArray();

        $this->donation_date = now()->format('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'user_id'        => 'nullable|exists:users,id',
            //'name'           => 'required|string|max:255',
            'amount'         => 'required|numeric|min:0.01',
            'category_id'    => 'required|exists:payment_categories,id',
            'pledge_id'      => 'nullable|exists:pledges,id',
            'payment_method' => 'required|string',
            'transaction_id' => 'nullable|string|max:255',
            'other'          => 'nullable|string|max:500',
            'donation_date'  => 'required|date',
        ];
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getPaymentsProperty()
    {
        $organization_id = auth()->user()->myOrganization->organization_id;

        return Payments::with(['user', 'category', 'pledge'])
            ->where('organization_id', $organization_id)
            ->when($this->search, fn ($q) =>
                $q->where(fn ($q) =>
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('transaction_id', 'like', "%{$this->search}%")
                )
            )
            ->when($this->filterCategory, fn ($q) =>
                $q->where('category_id', $this->filterCategory)
            )
            ->when($this->filterMethod, fn ($q) =>
                $q->where('payment_method', $this->filterMethod)
            )
            ->latest('donation_date')
            ->paginate(10);
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function updatingSearch(): void         { $this->resetPage(); }
    public function updatingFilterCategory(): void { $this->resetPage(); }
    public function updatingFilterMethod(): void   { $this->resetPage(); }

    // Update pledge options when member changes
    public function updatedUserId(): void
    {
        $this->pledge_id    = null;
        $this->pledgeOptions = [];

        if (! $this->user_id) return;

        $organization_id = auth()->user()->myOrganization->organization_id;

        $this->pledgeOptions = Pledge::where('organization_id', $organization_id)
            ->where('user_id', $this->user_id)
            ->whereIn('status', ['pending', 'partially_fulfilled'])
            ->with('project')
            ->get()
            ->map(fn ($p) => [
                'value' => $p->id,
                'label' => "{$p->project->project_title} — {$p->amount} (bal: {$p->balance})",
            ])
            ->toArray();
    }

    // Auto-fill donor name when member is selected
    public function updatedCategoryId(): void
    {
        $this->pledge_id = null;
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
        $payment = Payments::findOrFail($id);

        $this->editingId      = $payment->id;
        $this->user_id        = $payment->user_id;
        $this->name           = $payment->name;
        $this->amount         = $payment->amount;
        $this->category_id    = $payment->category_id;
        $this->pledge_id      = $payment->pledge_id;
        $this->payment_method = $payment->payment_method;
        $this->transaction_id = $payment->transaction_id ?? '';
        $this->other          = $payment->other ?? '';
        $this->donation_date  = $payment->donation_date;

        if ($this->user_id) {
            $this->updatedUserId();
        }

        $organization_id = auth()->user()->myOrganization->organization_id;

        $this->pledgeOptions = Pledge::where('organization_id', $organization_id)
            ->where('user_id', $this->user_id)
            ->whereIn('status', ['pending', 'partially_fulfilled'])
            ->with('project')
            ->get()
            ->map(fn ($p) => [
                'value' => $p->id,
                'label' => "{$p->project->project_title} — {$p->amount} (bal: {$p->balance})",
            ])
            ->toArray();

        $this->modalOpen = true;
    }

    public function save(): void
    {
        $this->validate();

        $organization_id = auth()->user()->myOrganization->organization_id;

        $data = [
            'organization_id' => $organization_id,
            'user_id'         => $this->user_id,
            //'name'            => $this->name,
            'amount'          => $this->amount,
            'category_id'     => $this->category_id,
            'pledge_id'       => $this->pledge_id ?: null,
            'payment_method'  => $this->payment_method,
            'transaction_id'  => $this->transaction_id ?: null,
            'other'           => $this->other ?: null,
            'donation_date'   => $this->donation_date,
        ];

        DB::transaction(function () use ($data) {
    $oldPledgeId = null;

    if ($this->editingId) {
        $payment = Payments::findOrFail($this->editingId);
        $oldPledgeId = $payment->pledge_id;

        $payment->update($data);

        $this->notification()->success('Payment updated', 'The payment record has been updated.');
    } else {
        $payment = Payments::create($data);

        $this->notification()->success('Payment recorded', 'New payment has been saved successfully.');
    }

    $pledgeIds = array_filter(array_unique([$oldPledgeId, $payment->pledge_id]));

    foreach ($pledgeIds as $pledgeId) {
        $pledge = Pledge::find($pledgeId);

        if (!$pledge) {
            continue;
        }

        $totalPaid = Payments::where('pledge_id', $pledge->id)->sum('amount');

        if ($totalPaid >= $pledge->amount) {
            $pledge->status = 'fulfilled';
        } elseif ($totalPaid > 0) {
            $pledge->status = 'partially_fulfilled';
        } else {
            $pledge->status = 'pending';
        }
        $pledge->fulfilled_amount = $totalPaid;

        $pledge->save();
    }
});

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

        Payments::findOrFail($this->deletingId)->delete();

        $this->notification()->success('Payment deleted', 'The payment record has been removed.');
        $this->confirmDeleteOpen = false;
        $this->deletingId        = null;
        $this->resetPage();
    }

    public function toggleReconcile(int $id): void
    {
        $payment = Payments::findOrFail($id);

        $payment->update([
            'reconciled'    => ! $payment->reconciled,
            'reconciled_at' => ! $payment->reconciled ? now() : null,
            'reconciled_by' => ! $payment->reconciled ? auth()->id() : null,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resetForm(): void
    {
        $this->editingId      = null;
        $this->user_id        = null;
        $this->name           = '';
        $this->amount         = '';
        $this->category_id    = null;
        $this->pledge_id      = null;
        $this->payment_method = '';
        $this->transaction_id = '';
        $this->other          = '';
        $this->donation_date  = now()->format('Y-m-d');
        $this->pledgeOptions  = [];
        $this->resetErrorBag();
    }
};
?>

<div class="min-h-screen bg-gray-50 p-6">

    {{-- ── Page Header ──────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Payments</h1>
            <p class="mt-1 text-sm text-gray-500">Record and manage all payment transactions</p>
        </div>
        <x-button wire:click="openCreate" label="Record Payment" icon="plus" primary />
    </div>

    {{-- ── Filters ──────────────────────────────────────────────────────────── --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-input
            wire:model.live.debounce.300ms="search"
            placeholder="Search donor or transaction ID…"
            icon="magnifying-glass"
            class="max-w-xs"
        />
        <x-select wire:model.live="filterCategory" class="max-w-[180px]" placeholder="All categories"
            :options="$categoryOptions" option-value="value" option-label="label"
        />
        <x-select wire:model.live="filterMethod" class="max-w-[160px]" placeholder="All methods"
            :options="collect($paymentMethods)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values()->toArray()"
            option-value="value" option-label="label"
        />
    </div>

    {{-- ── Table ────────────────────────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Member</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Method</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Date</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($this->payments as $payment)
                    <tr wire:key="pay-{{ $payment->id }}" class="transition hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <p class="font-medium text-gray-900">{{ $payment->user->name }}</p>
                            @if ($payment->transaction_id)
                                <p class="text-xs text-gray-400">Ref: {{ $payment->transaction_id }}</p>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                {{ $payment->category?->name ?? '—' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 font-semibold text-gray-900">
                            {{ format_currency($payment->amount, $this->currency) }}
                        </td>
                        <td class="px-6 py-4 capitalize text-gray-600">
                            {{ str_replace('_', ' ', $payment->payment_method) }}
                        </td>
                        <td class="px-6 py-4 text-gray-500">
                            {{ \Carbon\Carbon::parse($payment->donation_date)->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a
                                    href="{{ route('payment.receipt', $payment->id) }}"
                                    target="_blank"
                                    title="View Receipt"
                                    class="inline-flex items-center justify-center rounded p-1.5 text-gray-400 transition hover:bg-green-50 hover:text-green-600"
                                >
                                    <x-icon name="document-text" class="h-4 w-4" />
                                </a>
                                <button wire:click="toggleReconcile({{ $payment->id }})"
                                    title="{{ $payment->reconciled ? 'Mark as unreconciled' : 'Mark as reconciled' }}"
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-lg transition-colors {{ $payment->reconciled ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-400 hover:bg-gray-200' }}">
                                    <x-icon name="{{ $payment->reconciled ? 'check-circle' : 'ellipsis-horizontal-circle' }}" class="w-4 h-4" />
                                </button>
                                <x-mini-button wire:click="openEdit({{ $payment->id }})"      icon="pencil" flat secondary sm />
                                <x-mini-button wire:click="confirmDelete({{ $payment->id }})" icon="trash"  flat negative  sm />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <x-icon name="banknotes" class="h-10 w-10 opacity-40" />
                                <p class="text-sm font-medium">No payments found</p>
                                @if ($search || $filterCategory || $filterMethod)
                                    <p class="text-xs">Try adjusting your filters</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($this->payments->hasPages())
            <div class="border-t border-gray-100 px-6 py-4">
                {{ $this->payments->links() }}
            </div>
        @endif
    </div>

    {{-- ── Create / Edit Modal ─────────────────────────────────────────────── --}}
    <x-modal wire:model="modalOpen" max-width="2xl">
        <x-card :title="$editingId ? 'Edit Payment' : 'Record Payment'" class="relative">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                {{-- Member (optional) --}}
                <div class="sm:col-span-2">
                    <x-select
                        wire:model.live="user_id"
                        label="Member (optional)"
                        placeholder="Select a member or leave blank for walk-in"
                        :options="$memberOptions"
                        option-value="value"
                        option-label="label"
                        searchable
                        clearable
                    />
                </div>

                {{-- Donor name --}}
                {{--<div class="sm:col-span-2">
                    <x-input
                        wire:model="name"
                        label="Donor Name"
                        placeholder="Full name of the donor"
                        icon="user"
                    />
                </div>--}}

                {{-- Category --}}
                <x-select
                    wire:model.live="category_id"
                    label="Category"
                    placeholder="Select category"
                    :options="$categoryOptions"
                    option-value="value"
                    option-label="label"
                    searchable
                />

                {{-- Amount --}}
                <x-input
                    wire:model="amount"
                    label="Amount"
                    placeholder="0.00"
                    icon="currency-dollar"
                    type="number"
                    min="0"
                    step="0.01"
                />

                {{-- Pledge (shown only when a member is selected) --}}
                @if ($user_id && count($pledgeOptions))
                    <div class="sm:col-span-2">
                        <x-select
                            wire:model="pledge_id"
                            label="Link to Pledge (optional)"
                            placeholder="Select an open pledge"
                            :options="$pledgeOptions"
                            option-value="value"
                            option-label="label"
                            clearable
                        />
                    </div>
                @endif

                {{-- Payment Method --}}
                <x-select wire:model="payment_method" label="Payment Method" placeholder="Select method"
                    :options="collect($paymentMethods)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values()->toArray()"
                    option-value="value" option-label="label"
                />

                {{-- Donation Date --}}
                <x-datetime-picker
                    wire:model="donation_date"
                    label="Payment Date"
                    placeholder="Payment Date"
                    without-time
                    display-format="DD/MM/YYYY"
                />

                {{-- Transaction ID --}}
                <x-input
                    wire:model="transaction_id"
                    label="Transaction / Reference ID"
                    placeholder="Optional reference number"
                    icon="hashtag"
                />

                {{-- Notes --}}
                <div class="sm:col-span-2">
                    <x-textarea
                        wire:model="other"
                        label="Notes"
                        placeholder="Any additional notes…"
                        rows="2"
                    />
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button wire:click="$set('modalOpen', false)" label="Cancel" flat />
                    <x-button
                        wire:click="save"
                        wire:loading.attr="disabled"
                        label="{{ $editingId ? 'Update Payment' : 'Save Payment' }}"
                        primary
                        spinner="save"
                    />
                </div>
            </x-slot>
        </x-card>
    </x-modal>

    {{-- ── Confirm Delete ───────────────────────────────────────────────────── --}}
    <x-modal wire:model="confirmDeleteOpen" max-width="sm">
        <x-card title="Delete Payment" class="relative">
            <p class="text-sm text-gray-600">
                Are you sure you want to delete this payment record? This cannot be undone and may affect pledge balances.
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
