<?php

use App\Models\OfflinePaymentRequest;
use App\Models\Organization;
use App\Models\Payments;
use App\Notifications\OfflinePaymentStatusUpdated;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string  $filter           = 'pending';
    public string  $currency         = 'ZMW';
    public bool    $showRejectModal  = false;
    public ?int    $rejectingId      = null;
    public string  $rejectionReason  = '';

    public function mount(): void
    {
        $orgId          = auth()->user()->myOrganization->organization_id;
        $this->currency = Organization::find($orgId)?->currency ?? 'ZMW';
    }

    public function getOrgIdProperty(): int
    {
        return auth()->user()->myOrganization->organization_id;
    }

    public function getRequestsProperty()
    {
        return OfflinePaymentRequest::with(['user', 'category', 'reviewer'])
            ->where('organization_id', $this->orgId)
            ->when($this->filter !== 'all', fn ($q) => $q->where('status', $this->filter))
            ->latest()
            ->paginate(15);
    }

    public function getPendingCountProperty(): int
    {
        return OfflinePaymentRequest::where('organization_id', $this->orgId)->pending()->count();
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function approve(int $id): void
    {
        $request = OfflinePaymentRequest::where('organization_id', $this->orgId)
            ->where('status', 'pending')
            ->findOrFail($id);

        $payment = Payments::create([
            'organization_id' => $this->orgId,
            'user_id'         => $request->user_id,
            'category_id'     => $request->category_id,
            'amount'          => $request->amount,
            'name'            => $request->user?->name ?? 'Unknown',
            'payment_method'  => 'offline',
            'transaction_id'  => $request->reference,
            'donation_date'   => now()->toDateString(),
        ]);

        $request->update([
            'status'      => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'payment_id'  => $payment->id,
        ]);

        // Notify submitter
        if ($request->user && $request->user->email) {
            $request->user->notify(new OfflinePaymentStatusUpdated($request));
        }

        $this->notification()->success('Approved', 'Payment has been approved and recorded.');
    }

    public function openRejectModal(int $id): void
    {
        $this->rejectingId     = $id;
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public function reject(): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $request = OfflinePaymentRequest::where('organization_id', $this->orgId)
            ->where('status', 'pending')
            ->findOrFail($this->rejectingId);

        $request->update([
            'status'           => 'rejected',
            'reviewed_by'      => auth()->id(),
            'reviewed_at'      => now(),
            'rejection_reason' => $this->rejectionReason,
        ]);

        // Notify submitter
        if ($request->user && $request->user->email) {
            $request->user->notify(new OfflinePaymentStatusUpdated($request));
        }

        $this->showRejectModal = false;
        $this->rejectingId     = null;
        $this->rejectionReason = '';
        $this->notification()->success('Rejected', 'Submission has been rejected and the member notified.');
    }

    public function viewProof(int $id): void
    {
        $request = OfflinePaymentRequest::where('organization_id', $this->orgId)->findOrFail($id);
        $this->dispatch('open-proof', url: asset('storage/' . $request->proof_path));
    }
};
?>

<div class="min-h-screen bg-gray-50 p-6" x-data
     @open-proof.window="window.open($event.detail.url, '_blank')">

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Offline Payment Review</h1>
            <p class="mt-1 text-sm text-gray-500">Review and approve member offline payment submissions</p>
        </div>
        @if($this->pendingCount > 0)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-yellow-100 px-3 py-1 text-sm font-semibold text-yellow-800">
                <span class="h-2 w-2 rounded-full bg-yellow-500"></span>
                {{ $this->pendingCount }} pending
            </span>
        @endif
    </div>

    {{-- Filter tabs --}}
    <div class="mb-4 flex gap-2">
        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label)
            <button wire:click="$set('filter', '{{ $value }}')"
                    class="px-4 py-1.5 rounded-lg text-sm font-medium transition-colors
                        {{ $filter === $value ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Member</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Category</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Reference</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Submitted</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 bg-white">
                @forelse($this->requests as $req)
                    <tr class="hover:bg-gray-50" wire:key="rev-{{ $req->id }}">
                        <td class="px-6 py-4">
                            <p class="font-medium text-gray-900">{{ $req->user?->name ?? '—' }}</p>
                            <p class="text-xs text-gray-400">{{ $req->user?->email }}</p>
                        </td>
                        <td class="px-6 py-4 text-gray-600">{{ $req->category?->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-right font-semibold text-gray-900">{{ format_currency($req->amount, $currency) }}</td>
                        <td class="px-6 py-4 text-xs text-gray-500">{{ $req->reference ?? '—' }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $req->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-4 text-center">
                            @if($req->status === 'approved')
                                <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">Approved</span>
                            @elseif($req->status === 'rejected')
                                <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">Rejected</span>
                            @else
                                <span class="inline-flex rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-semibold text-yellow-700">Pending</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                {{-- View proof --}}
                                <button wire:click="viewProof({{ $req->id }})"
                                        class="rounded-lg bg-gray-50 border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                                    View Proof
                                </button>

                                @if($req->status === 'pending')
                                    <button wire:click="approve({{ $req->id }})" wire:loading.attr="disabled"
                                            class="rounded-lg bg-green-50 px-2.5 py-1.5 text-xs font-semibold text-green-700 hover:bg-green-100 transition-colors">
                                        Approve
                                    </button>
                                    <button wire:click="openRejectModal({{ $req->id }})"
                                            class="rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 transition-colors">
                                        Reject
                                    </button>
                                @elseif($req->status === 'rejected' && $req->rejection_reason)
                                    <span class="text-xs text-gray-400 italic max-w-[140px] truncate" title="{{ $req->rejection_reason }}">
                                        "{{ $req->rejection_reason }}"
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <x-icon name="check-circle" class="h-10 w-10 opacity-40" />
                                <p class="text-sm">No submissions found.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($this->requests->hasPages())
            <div class="border-t border-gray-100 px-6 py-4">
                {{ $this->requests->links() }}
            </div>
        @endif
    </div>

    {{-- Reject modal --}}
    <flux:modal wire:model="showRejectModal" name="reject-modal">
        <div class="space-y-4 p-6">
            <flux:heading size="lg">Reject Payment</flux:heading>
            <flux:text>Please provide a reason. The member will be notified by email.</flux:text>
            <flux:field>
                <flux:label>Reason for Rejection</flux:label>
                <flux:textarea wire:model="rejectionReason" rows="3" placeholder="e.g. Proof is unclear, amount doesn't match records…" />
                <flux:error name="rejectionReason" />
            </flux:field>
            <div class="flex gap-3 justify-end">
                <flux:button wire:click="$set('showRejectModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="reject" variant="danger">Confirm Rejection</flux:button>
            </div>
        </div>
    </flux:modal>
    <x-spinner/>
</div>
