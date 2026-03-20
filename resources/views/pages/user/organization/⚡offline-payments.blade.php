<?php

use App\Models\OfflinePaymentRequest;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\PaymentCategory;
use App\Models\User;
use App\Notifications\OfflinePaymentSubmitted;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads, WithPagination;

    public string  $currency    = 'ZMW';
    public float   $amount      = 0;
    public ?int    $category_id = null;
    public string  $reference   = '';
    public string  $notes       = '';
    public         $proof       = null;
    public bool    $showForm    = false;

    public function mount(): void
    {
        $orgId          = auth()->user()->myOrganization->organization_id;
        $this->currency = Organization::find($orgId)?->currency ?? 'ZMW';
    }

    public function getOrgIdProperty(): int
    {
        return auth()->user()->myOrganization->organization_id;
    }

    public function getCategoriesProperty()
    {
        return PaymentCategory::where('organization_id', $this->orgId)->orderBy('name')->get();
    }

    public function getMyRequestsProperty()
    {
        return OfflinePaymentRequest::with('category')
            ->where('organization_id', $this->orgId)
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(10);
    }

    public function openForm(): void
    {
        $this->resetExcept('currency');
        $this->showForm = true;
    }

    public function submit(): void
    {
        $this->validate([
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'category_id' => ['nullable', 'exists:payment_categories,id'],
            'reference'   => ['nullable', 'string', 'max:255'],
            'notes'       => ['nullable', 'string', 'max:1000'],
            'proof'       => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $this->proof->store('offline-payment-proofs', 'public');

        $request = OfflinePaymentRequest::create([
            'organization_id' => $this->orgId,
            'user_id'         => auth()->id(),
            'category_id'     => $this->category_id,
            'amount'          => $this->amount,
            'reference'       => $this->reference ?: null,
            'notes'           => $this->notes ?: null,
            'proof_path'      => $path,
        ]);

        // Notify org admins
        $orgOwnerId = Organization::find($this->orgId)?->owner_id;
        $owner      = $orgOwnerId ? User::find($orgOwnerId) : null;

        if ($owner && $owner->email) {
            $owner->notify(new OfflinePaymentSubmitted($request));
        }

        $this->showForm = false;
        $this->reset('amount', 'category_id', 'reference', 'notes', 'proof');
        $this->notification()->success('Submitted', 'Your payment proof has been submitted for review.');
    }
};
?>

<div class="min-h-screen bg-gray-50 p-6">

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Offline Payments</h1>
            <p class="mt-1 text-sm text-gray-500">Submit proof of a payment made outside the system (bank transfer, cash deposit, etc.)</p>
        </div>
        <flux:button wire:click="openForm" icon="plus" variant="primary">Submit Payment Proof</flux:button>
    </div>

    {{-- Submission form --}}
    @if($showForm)
    <div class="mb-6 rounded-xl border border-blue-100 bg-white p-6 shadow-sm">
        <h2 class="mb-5 text-base font-semibold text-gray-900">New Offline Payment Submission</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Amount <span class="text-gray-400 font-normal">({{ $currency }})</span></flux:label>
                <flux:input wire:model="amount" type="number" step="0.01" min="0" placeholder="0.00" />
                <flux:error name="amount" />
            </flux:field>

            <flux:field>
                <flux:label>Category</flux:label>
                <flux:select wire:model="category_id">
                    <option value="">— Select category —</option>
                    @foreach($this->categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="category_id" />
            </flux:field>

            <flux:field>
                <flux:label>Transaction Reference</flux:label>
                <flux:input wire:model="reference" placeholder="e.g. bank ref, mobile money receipt no." />
                <flux:error name="reference" />
            </flux:field>

            <flux:field>
                <flux:label>Proof of Payment <span class="text-gray-400 font-normal">(JPG, PNG or PDF, max 5MB)</span></flux:label>
                <input type="file" wire:model="proof" accept=".jpg,.jpeg,.png,.pdf"
                       class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100" />
                <div wire:loading wire:target="proof" class="mt-1 text-xs text-gray-400">Uploading…</div>
                <flux:error name="proof" />
            </flux:field>
        </div>

        <flux:field class="mt-4">
            <flux:label>Notes <span class="text-gray-400 font-normal">(optional)</span></flux:label>
            <flux:textarea wire:model="notes" rows="2" placeholder="Any additional details…" />
            <flux:error name="notes" />
        </flux:field>

        <div class="mt-5 flex gap-3">
            <flux:button wire:click="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">Submit for Review</span>
                <span wire:loading wire:target="submit">Submitting…</span>
            </flux:button>
            <flux:button wire:click="$set('showForm', false)" variant="ghost">Cancel</flux:button>
        </div>
    </div>
    @endif

    {{-- Submissions history --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-700">My Submissions</h2>
        </div>
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Category</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Reference</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 bg-white">
                @forelse($this->myRequests as $req)
                    <tr class="hover:bg-gray-50" wire:key="opr-{{ $req->id }}">
                        <td class="px-6 py-4 text-gray-600">{{ $req->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-4 text-gray-700">{{ $req->category?->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-right font-semibold text-gray-900">{{ format_currency($req->amount, $currency) }}</td>
                        <td class="px-6 py-4 text-gray-500 text-xs">{{ $req->reference ?? '—' }}</td>
                        <td class="px-6 py-4 text-center">
                            @if($req->status === 'approved')
                                <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">Approved</span>
                            @elseif($req->status === 'rejected')
                                <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700" title="{{ $req->rejection_reason }}">Rejected</span>
                            @else
                                <span class="inline-flex rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-semibold text-yellow-700">Pending</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-400">
                                <x-icon name="document-arrow-up" class="h-10 w-10 opacity-40" />
                                <p class="text-sm">No submissions yet. Use the button above to submit proof of a payment.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($this->myRequests->hasPages())
            <div class="border-t border-gray-100 px-6 py-4">
                {{ $this->myRequests->links() }}
            </div>
        @endif
    </div>
    <x-spinner/>
</div>
