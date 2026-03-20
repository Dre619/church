<?php

use App\Models\Organization;
use App\Models\OrganizationPayment;
use App\Models\Plan;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new class extends Component
{
    use WithPagination, WireUiActions;

    public $showModal = false;
    public $editMode = false;
    public $deleteModal = false;
    public $viewModal = false;

    // Model properties
    public $paymentId;
    public $organization_id;
    public $plan_id;
    public $amount;
    public $payment_method = 'credit_card';
    public $transaction_id;
    public $paid_at;
    public $status = 'pending';

    // View modal
    public $viewPayment;

    // Search and filters
    public $search = '';
    public $perPage = 10;
    public $sortField = 'created_at';
    public $sortDirection = 'desc';
    public $filterStatus = 'all';
    public $filterOrganization = '';
    public $filterPaymentMethod = 'all';

    public $paymentMethods = [
        'credit_card' => 'Credit Card',
        'debit_card' => 'Debit Card',
        'bank_transfer' => 'Bank Transfer',
        'paypal' => 'PayPal',
        'stripe' => 'Stripe',
        'cash' => 'Cash',
        'check' => 'Check',
        'other' => 'Other',
    ];

    public $statuses = [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
        'cancelled' => 'Cancelled',
    ];

    protected function rules()
    {
        return [
            'organization_id' => 'required|exists:organizations,id',
            'plan_id' => 'required|exists:plans,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'transaction_id' => 'nullable|string|max:255',
            'paid_at' => 'required|date',
            'status' => 'required|string',
        ];
    }

    public function mount()
    {
        $this->paid_at = now()->format('Y-m-d H:i');
    }

    public function getMonthlyRevenueProperty()
    {
        return OrganizationPayment::where('status', 'completed')
            ->whereMonth('paid_at', now()->month)
            ->sum('amount');
    }

    public function getPendingAmountProperty()
    {
        return OrganizationPayment::where('status', 'pending')->sum('amount');
    }

    public function gettotalRevenueProperty()
    {
        return OrganizationPayment::where('status', 'completed')->sum('amount');
    }

    public function getPlansProperty()
    {
        return Plan::where('is_active', true)->orderBy('name')->get();
    }

    public function getOrganizationsProperty()
    {
        return Organization::orderBy('name')->get();
    }

    public function getPaymentsProperty()
    {
        return OrganizationPayment::query()
            ->with(['organization', 'plan'])
            ->when($this->search, function ($query) {
                $query->whereHas('organization', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                })->orWhere('transaction_id', 'like', '%' . $this->search . '%');
            })
            ->when($this->filterStatus !== 'all', function ($query) {
                $query->where('status', $this->filterStatus);
            })
            ->when($this->filterOrganization, function ($query) {
                $query->where('organization_id', $this->filterOrganization);
            })
            ->when($this->filterPaymentMethod !== 'all', function ($query) {
                $query->where('payment_method', $this->filterPaymentMethod);
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $payment = OrganizationPayment::findOrFail($id);

        $this->paymentId = $payment->id;
        $this->organization_id = $payment->organization_id;
        $this->plan_id = $payment->plan_id;
        $this->amount = $payment->amount;
        $this->payment_method = $payment->payment_method;
        $this->transaction_id = $payment->transaction_id;
        $this->paid_at = $payment->paid_at ? Carbon::parse($payment->paid_at)->format('Y-m-d H:i') : null;
        $this->status = $payment->status;

        $this->editMode = true;
        $this->showModal = true;
    }

    public function viewPayment($id)
    {
        $this->viewPayment = OrganizationPayment::with(['organization', 'plan'])->findOrFail($id);
        $this->viewModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'organization_id' => $this->organization_id,
            'plan_id' => $this->plan_id,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'transaction_id' => $this->transaction_id,
            'paid_at' => $this->paid_at,
            'status' => $this->status,
        ];

        if ($this->editMode) {
            $payment = OrganizationPayment::findOrFail($this->paymentId);
            $payment->update($data);
            $this->notification()->success('Success', 'Payment updated successfully');
        } else {
            OrganizationPayment::create($data);
            $this->notification()->success('Success', 'Payment recorded successfully');
        }

        $this->resetForm();
        $this->showModal = false;
    }

    public function updateStatus($id, $status)
    {
        $payment = OrganizationPayment::findOrFail($id);
        $payment->update(['status' => $status]);

        $this->notification()->success('Success', 'Payment status updated to ' . $this->statuses[$status]);
    }

    public function confirmDelete($id)
    {
        $this->paymentId = $id;
        $this->deleteModal = true;
    }

    public function delete()
    {
        $payment = OrganizationPayment::findOrFail($this->paymentId);
        $payment->delete();

        $this->deleteModal = false;
        $this->notification()->success('Success', 'Payment deleted successfully');
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset([
            'paymentId',
            'organization_id',
            'plan_id',
            'amount',
            'payment_method',
            'transaction_id',
            'status',
            'editMode',
        ]);
        $this->paid_at = now()->format('Y-m-d H:i');
        $this->payment_method = 'credit_card';
        $this->status = 'pending';
        $this->resetValidation();
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }
};
?>
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">Organization Payments</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">Track and manage all organization subscription payments</p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-sm p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm font-medium">Total Revenue</p>
                    <h3 class="text-3xl font-bold mt-1">{{ format_currency($this->totalRevenue) }}</h3>
                </div>
                <x-icon name="currency-dollar" class="w-12 h-12 text-green-200" />
            </div>
        </div>

        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-sm p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm font-medium">This Month</p>
                    <h3 class="text-3xl font-bold mt-1">{{ format_currency($this->monthlyRevenue) }}</h3>
                </div>
                <x-icon name="chart-bar" class="w-12 h-12 text-blue-200" />
            </div>
        </div>

        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-lg shadow-sm p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-100 text-sm font-medium">Pending</p>
                    <h3 class="text-3xl font-bold mt-1">{{ format_currency($this->pendingAmount) }}</h3>
                </div>
                <x-icon name="clock" class="w-12 h-12 text-yellow-200" />
            </div>
        </div>
    </div>

    {{-- Actions Bar --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex-1 max-w-md">
                    <x-input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search by organization or transaction ID..."
                        icon="magnifying-glass"
                    />
                </div>

                <div class="flex items-center gap-3">
                    <x-button
                        primary
                        icon="plus"
                        wire:click="create"
                    >
                        Record Payment
                    </x-button>
                </div>
            </div>

            {{-- Filters Row --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <x-select
                    wire:model.live="filterStatus"
                    placeholder="Filter by status"
                >
                    <x-select.option label="All Status" value="all" />
                    @foreach($statuses as $key => $label)
                        <x-select.option label="{{ $label }}" value="{{ $key }}" />
                    @endforeach
                </x-select>

                <x-select
                    wire:model.live="filterPaymentMethod"
                    placeholder="Payment method"
                >
                    <x-select.option label="All Methods" value="all" />
                    @foreach($paymentMethods as $key => $label)
                        <x-select.option label="{{ $label }}" value="{{ $key }}" />
                    @endforeach
                </x-select>

                <x-select
                    wire:model.live="filterOrganization"
                    placeholder="Filter by organization"
                    :options="$this->organizations"
                    option-label="name"
                    option-value="id"
                />

                <x-select
                    wire:model.live="perPage"
                    placeholder="Per page"
                >
                    <x-select.option label="10" value="10" />
                    <x-select.option label="25" value="25" />
                    <x-select.option label="50" value="50" />
                    <x-select.option label="100" value="100" />
                </x-select>
            </div>
        </div>
    </div>

    {{-- Payments Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Transaction ID
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Organization
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Plan
                        </th>
                        <th scope="col" class="px-6 py-3 text-left">
                            <button wire:click="sortBy('amount')" class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider hover:text-gray-700 dark:hover:text-gray-200">
                                Amount
                                @if($sortField === 'amount')
                                    <x-icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Method
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left">
                            <button wire:click="sortBy('paid_at')" class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider hover:text-gray-700 dark:hover:text-gray-200">
                                Paid At
                                @if($sortField === 'paid_at')
                                    <x-icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4" />
                                @endif
                            </button>
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($this->payments as $payment)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-mono text-gray-900 dark:text-gray-100">
                                    {{ $payment->transaction_id ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    @if($payment->organization->logo)
                                        <img src="{{ Storage::url($payment->organization->logo) }}" alt="{{ $payment->organization->name }}" class="h-8 w-8 rounded-full object-cover mr-2">
                                    @else
                                        <div class="h-8 w-8 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center mr-2">
                                            <span class="text-white font-semibold text-xs">{{ substr($payment->organization->name, 0, 2) }}</span>
                                        </div>
                                    @endif
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $payment->organization->name }}
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ $payment->plan->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ number_format($payment->amount, 2) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-badge flat>
                                    {{ $paymentMethods[$payment->payment_method] ?? $payment->payment_method }}
                                </x-badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $statusColors = [
                                        'pending' => 'warning',
                                        'completed' => 'positive',
                                        'failed' => 'negative',
                                        'refunded' => 'info',
                                        'cancelled' => 'flat',
                                    ];
                                    $statusColor = $statusColors[$payment->status] ?? 'flat';
                                @endphp
                               <x-badge
    :positive="$statusColor === 'positive'"
    :warning="$statusColor === 'warning'"
    :negative="$statusColor === 'negative'"
    :info="$statusColor === 'info'"
    :flat="$statusColor === 'flat'"
>
    {{ $statuses[$payment->status] ?? $payment->status }}
</x-badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $payment->paid_at ? \Carbon\Carbon::parse($payment->paid_at)->format('M d, Y H:i') : 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    @if($payment->status === 'pending')
                                        <x-button
                                            xs
                                            positive
                                            icon="check"
                                            wire:click="updateStatus({{ $payment->id }}, 'completed')"
                                        />
                                    @endif
                                    <x-button
                                        xs
                                        info
                                        icon="eye"
                                        wire:click="viewPayment({{ $payment->id }})"
                                    />
                                    <x-button
                                        xs
                                        primary
                                        icon="pencil"
                                        wire:click="edit({{ $payment->id }})"
                                    />
                                    <x-button
                                        xs
                                        negative
                                        icon="trash"
                                        wire:click="confirmDelete({{ $payment->id }})"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <x-icon name="credit-card" class="w-12 h-12 text-gray-400 mb-3" />
                                    <p class="text-gray-500 dark:text-gray-400">No payments found</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $this->payments->links() }}
        </div>
    </div>

    {{-- Create/Edit Modal --}}
    @if($showModal)
    <x-modal wire:model.defer="showModal" max-width="2xl">
        <x-card title="{{ $editMode ? 'Edit Payment' : 'Record New Payment' }}" class="relative ">
            <form wire:submit.prevent="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Organization --}}
                    <div class="col-span-2">
                        <x-select
                            label="Organization *"
                            placeholder="Select organization"
                            wire:model="organization_id"
                            :options="$this->organizations"
                            option-label="name"
                            option-value="id"
                        />
                        @error('organization_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Plan --}}
                    <div class="col-span-2">
                        <x-select
                            label="Plan *"
                            placeholder="Select plan"
                            wire:model="plan_id"
                            :options="$this->plans"
                            option-label="name"
                            option-value="id"
                        />
                        @error('plan_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Amount --}}
                    <div>
                        <x-currency
                            label="Amount *"
                            placeholder="0.00"
                            wire:model="amount"
                            prefix="$"
                            thousands=","
                            decimal="."
                            precision="2"
                        />
                        @error('amount') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Payment Method --}}
                    <div>
                        <x-select
                            label="Payment Method *"
                            placeholder="Select method"
                            wire:model="payment_method"
                        >
                            @foreach($paymentMethods as $key => $label)
                                <x-select.option label="{{ $label }}" value="{{ $key }}" />
                            @endforeach
                        </x-select>
                        @error('payment_method') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Transaction ID --}}
                    <div>
                        <x-input
                            label="Transaction ID"
                            placeholder="TXN-123456"
                            wire:model="transaction_id"
                        />
                        @error('transaction_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Status --}}
                    <div>
                        <x-select
                            label="Status *"
                            placeholder="Select status"
                            wire:model="status"
                        >
                            @foreach($statuses as $key => $label)
                                <x-select.option label="{{ $label }}" value="{{ $key }}" />
                            @endforeach
                        </x-select>
                        @error('status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Paid At --}}
                    <div class="col-span-2">
                        <x-datetime-picker
                            label="Payment Date & Time *"
                            placeholder="Select date and time"
                            wire:model="paid_at"
                        />
                        @error('paid_at') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                <x-slot name="footer">
                    <div class="flex justify-end gap-3">
                        <x-button flat label="Cancel" wire:click="closeModal" />
                        <x-button primary label="{{ $editMode ? 'Update' : 'Record' }}" type="submit" spinner="save" />
                    </div>
                </x-slot>
            </form>
        </x-card>
    </x-modal>
    @endif
    {{-- View Payment Modal --}}
    @if($viewModal)
    <x-modal wire:model.defer="viewModal" max-width="2xl">
        @if($viewPayment)
            <x-card title="Payment Details" class="relative">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Organization</label>
                        <p class="text-gray-900 dark:text-gray-100">{{ $viewPayment->organization->name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Plan</label>
                        <p class="text-gray-900 dark:text-gray-100">{{ $viewPayment->plan->name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount</label>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">${{ number_format($viewPayment->amount, 2) }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        @php
                            $statusColors = [
                                'pending' => 'warning',
                                'completed' => 'positive',
                                'failed' => 'negative',
                                'refunded' => 'info',
                                'cancelled' => 'flat',
                            ];
                            $statusColor = $statusColors[$viewPayment->status] ?? 'flat';
                        @endphp
                        <x-badge
                            :positive="$statusColor === 'positive'"
                            :warning="$statusColor === 'warning'"
                            :negative="$statusColor === 'negative'"
                            :info="$statusColor === 'info'"
                            :flat="$statusColor === 'flat'"
                        >
                            {{ $statuses[$payment->status] ?? $payment->status }}
                        </x-badge>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Method</label>
                        <p class="text-gray-900 dark:text-gray-100">{{ $paymentMethods[$viewPayment->payment_method] ?? $viewPayment->payment_method }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Transaction ID</label>
                        <p class="text-gray-900 dark:text-gray-100 font-mono">{{ $viewPayment->transaction_id ?? 'N/A' }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Paid At</label>
                        <p class="text-gray-900 dark:text-gray-100">{{ $viewPayment->paid_at ? \Carbon\Carbon::parse($viewPayment->paid_at)->format('M d, Y H:i A') : 'N/A' }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Recorded At</label>
                        <p class="text-gray-900 dark:text-gray-100">{{ $viewPayment->created_at->format('M d, Y H:i A') }}</p>
                    </div>
                </div>

                <x-slot name="footer">
                    <div class="flex justify-end">
                        <x-button flat label="Close" wire:click="$set('viewModal', false)" />
                    </div>
                </x-slot>
            </x-card>
        @endif
    </x-modal>
    @endif
    {{-- Delete Confirmation Modal --}}
    @if($deleteModal)
    <x-modal wire:model.defer="deleteModal" max-width="md">
        <x-card class="relative">
            <div class="text-center">
                <x-icon name="exclamation-circle" class="w-16 h-16 text-red-500 mx-auto mb-4" />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Delete Payment</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">Are you sure you want to delete this payment record? This action cannot be undone.</p>
            </div>

            <x-slot name="footer">
                <div class="flex justify-center gap-3">
                    <x-button flat label="Cancel" wire:click="$set('deleteModal', false)" />
                    <x-button negative label="Delete" wire:click="delete" spinner="delete" />
                </div>
            </x-slot>
        </x-card>
    </x-modal>
    @endif
</div>
