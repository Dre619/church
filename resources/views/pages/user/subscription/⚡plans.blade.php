<?php

use App\Models\OrganizationPayment;
use App\Models\OrganizationPlan;
use App\Models\SubscriptionPaymentRequest;
use App\Models\User;
use App\Notifications\SubscriptionPaymentSubmitted;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    use WithFileUploads;

    public $currentPlan;
    public $selectedPlan;
    public int $months = 1;

    // Offline payment fields
    public bool   $showOfflineForm  = false;
    public string $offlineReference = '';
    public string $offlineNotes     = '';
    public        $offlineProof     = null;

    // Month options with optional discount labels
    public array $monthOptions = [
        1  => ['label' => '1 Month',   'discount' => 0],
        3  => ['label' => '3 Months',  'discount' => 0],
        6  => ['label' => '6 Months',  'discount' => 10],
        12 => ['label' => '12 Months', 'discount' => 20],
    ];

    public function mount(): void
    {
        $plan = auth()->user()?->myOrganization?->organization?->activePlan;
        $this->checkifFirstTime();
        $this->currentPlan  = $plan?->plan_id;
        $this->selectedPlan = $this->currentPlan;
    }

    private function checkifFirstTime()
    {
         if(auth()->user()?->myOrganization?->organization->plans->count()<=0)
            {
                $trialPlan = Plan::where('is_trial',true)->first();
                auth()->user()->myOrganization->organization->plans()->create([
                    'plan_id' => $trialPlan->id,
                    'start_date' => now(),
                    'end_date' => now()->addDays($trialPlan->trial_days),
                    'is_active' => true,
                ]);
                return redirect()->route('dashboard');
            }
    }
    public function getPlansProperty()
    {
        //->where('is_trial', false)
        return Plan::where('is_active', true)->orderBy('price')->get();
    }

    public function getSelectedPlanModelProperty(): ?Plan
    {
        return $this->selectedPlan
            ? $this->plans->firstWhere('id', $this->selectedPlan)
            : null;
    }

    /** Price per month after discount */
    public function getMonthlyPriceProperty(): float
    {
        if (! $this->selectedPlanModel) return 0;
        $discount = $this->monthOptions[$this->months]['discount'] ?? 0;
        return $this->selectedPlanModel->price * (1 - $discount / 100);
    }

    /** Total charge sent to Lenco (in ZMW, multiplied by 100 for kobo/ngwe if needed) */
    public function getTotalAmountProperty(): float
    {
        return round($this->monthlyPrice * $this->months, 2);
    }

    public function selectPlan(int $planId): void
    {
        $this->selectedPlan = $planId;
        // Don't open the modal here — user must choose duration first
    }

    /** Validate then dispatch to JS — JS launches the Lenco widget */
    public function proceedToPayment(): void
    {
    /*
    $this->validate([
            'selectedPlan' => 'required|exists:plans,id',
            'months'       => 'required|in:1,3,6,12',
        ]);
*/
        $user = auth()->user();
        $name = explode(' ', $user?->name ?? '');
        $data = [
            'amount'    => (float) $this->totalAmount,
            'planId'    => $this->selectedPlan,
            'months'    => $this->months,
            'email'     => $user?->email ?? '',
            'firstName' => $user?->first_name ?? ($name[0] ?? ''),
            'lastName'  => $user?->last_name  ?? ($name[1] ?? ''),
        ];
        $this->dispatch('launchLencoPay', $data);
    }

    /**
     * Called from JS after Lenco returns a successful reference.
     * Creates the subscription server-side — no session or form POST needed.
     */
    public function completeSubscription(string $reference): void
    {
        $this->validate([
            'selectedPlan' => 'required|exists:plans,id',
            'months'       => 'required|in:1,3,6,12',
        ]);

        $plan = Plan::findOrFail($this->selectedPlan);

       DB::transaction(function()use($plan,$reference){
        $organization_id = auth()->user()?->myOrganization?->organization_id;
             OrganizationPlan::create([
                'organization_id'   => $organization_id,
                'plan_id'           => $plan->id,
                'months'            => $this->months,
                'amount_paid'       => $this->totalAmount,
                'discount'          => $this->monthOptions[$this->months]['discount'],
                'status'            => 'active',
                'start_date'         => now(),
                'end_date'           => now()->addMonths($this->months),
                'payment_reference' => $reference,
            ]);
            OrganizationPayment::create([
                'organization_id' => $organization_id,
                'plan_id'=> $plan->id,
                'amount' => $this->totalAmount,
                'payment_method' => 'lenco',
                'transaction_id' => $reference,
                'status' => 'paid',
                'paid_at' => now(),
            ]);
       });

        $this->currentPlan  = $plan->id;
        $this->selectedPlan = $plan->id;

        $this->dispatch('subscription-updated');
    }

    public function submitOfflinePayment(): void
    {
        $this->validate([
            'selectedPlan'     => 'required|exists:plans,id',
            'months'           => 'required|in:1,3,6,12',
            'offlineReference' => ['nullable', 'string', 'max:255'],
            'offlineNotes'     => ['nullable', 'string', 'max:1000'],
            'offlineProof'     => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $plan           = Plan::findOrFail($this->selectedPlan);
        $organizationId = auth()->user()?->myOrganization?->organization_id;

        $path = $this->offlineProof->store('subscription-payment-proofs', 'public');

        $subRequest = SubscriptionPaymentRequest::create([
            'organization_id' => $organizationId,
            'plan_id'         => $plan->id,
            'months'          => $this->months,
            'amount'          => $this->totalAmount,
            'reference'       => $this->offlineReference ?: null,
            'notes'           => $this->offlineNotes ?: null,
            'proof_path'      => $path,
        ]);

        // Notify super admins (role = admin)
        User::where('role', 'admin')->each(function (User $admin) use ($subRequest): void {
            if ($admin->email) {
                $admin->notify(new SubscriptionPaymentSubmitted($subRequest));
            }
        });

        $this->showOfflineForm  = false;
        $this->offlineReference = '';
        $this->offlineNotes     = '';
        $this->offlineProof     = null;

        $this->notification()->success('Submitted', 'Your payment proof has been submitted. We will activate your plan once reviewed.');
    }

    public function getMyOfflineRequestsProperty()
    {
        $orgId = auth()->user()?->myOrganization?->organization_id;

        return SubscriptionPaymentRequest::with('plan')
            ->where('organization_id', $orgId)
            ->latest()
            ->limit(5)
            ->get();
    }
};
?>

<div class="min-h-screen bg-slate-50"
     style="font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif;">

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        .mono { font-family: 'DM Mono', ui-monospace, monospace; }
        .plan-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .plan-card:hover { transform: translateY(-4px); }
        .plan-card.selected { transform: translateY(-6px); }
        .glow-emerald { box-shadow: 0 0 40px rgba(16,185,129,.15), 0 0 80px rgba(16,185,129,.05); }
        .glow-sky     { box-shadow: 0 0 40px rgba(14,165,233,.15), 0 0 80px rgba(14,165,233,.05); }
        .glow-violet  { box-shadow: 0 0 40px rgba(139,92,246,.15), 0 0 80px rgba(139,92,246,.05); }
        .badge-current { animation: pulse-badge 2s ease-in-out infinite; }
        @keyframes pulse-badge { 0%,100%{opacity:1} 50%{opacity:.7} }
        @keyframes fade-in-up { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
        .animate-fade-in-up { animation: fade-in-up 0.4s ease forwards; }

        /* Month pill selector */
        .month-pill {
            position: relative;
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            border: 1.5px solid #cbd5e1;
            font-size: 0.8rem;
            font-weight: 500;
            color: #64748b;
            transition: border-color .2s, color .2s, background .2s;
            white-space: nowrap;
        }
        .month-pill:hover { border-color: #94a3b8; color: #334155; }
        .month-pill.active {
            border-color: #10b981;
            background: rgba(16,185,129,.08);
            color: #059669;
        }
        .month-pill .discount-tag {
            position: absolute;
            top: -8px; right: -4px;
            background: #10b981;
            color: #fff;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 9999px;
            letter-spacing: .04em;
        }

        /* Price summary bar */
        .summary-bar {
            background: linear-gradient(135deg, rgba(16,185,129,.06) 0%, rgba(14,165,233,.04) 100%);
            border: 1px solid rgba(16,185,129,.2);
        }
    </style>

    <div class="max-w-6xl mx-auto px-4 py-16 sm:px-6 lg:px-8">

        {{-- ── Header ───────────────────────────────────────────────────── --}}
        <div class="text-center mb-14 animate-fade-in-up">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white border border-slate-200 text-xs text-slate-500 mono mb-5 shadow-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block"></span>
                SUBSCRIPTION MANAGEMENT
            </span>
            <h1 class="text-4xl sm:text-5xl font-semibold tracking-tight text-slate-900 mb-4">Choose your plan</h1>
            <p class="text-slate-500 text-lg max-w-xl mx-auto">
                Pick a plan and how long you'd like to subscribe. Longer terms unlock bigger savings.
            </p>
        </div>

        {{-- ── Plan Cards ───────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
            @foreach($this->plans as $index => $plan)
                @php
                    $isCurrentPlan = $plan->id === $currentPlan;
                    $isSelected    = $plan->id === $selectedPlan;
                    $isFeatured    = $index === 1;
                    $colorSchemes  = [
                        0 => ['border'=>'border-slate-200','accent'=>'text-sky-500',    'btn'=>'bg-sky-500 hover:bg-sky-400',    'badge'=>'bg-sky-50 text-sky-600 border-sky-200',    'glow'=>'glow-sky',    'check'=>'text-sky-500',    'ring'=>'ring-sky-500/40'],
                        1 => ['border'=>'border-slate-200','accent'=>'text-emerald-500','btn'=>'bg-emerald-500 hover:bg-emerald-400','badge'=>'bg-emerald-50 text-emerald-600 border-emerald-200','glow'=>'glow-emerald','check'=>'text-emerald-500','ring'=>'ring-emerald-500/40'],
                        2 => ['border'=>'border-slate-200','accent'=>'text-violet-500', 'btn'=>'bg-violet-500 hover:bg-violet-400','badge'=>'bg-violet-50 text-violet-600 border-violet-200','glow'=>'glow-violet','check'=>'text-violet-500','ring'=>'ring-violet-500/40'],
                    ];
                    $c = $colorSchemes[$index % 3];
                @endphp

                <div wire:key="plan-{{ $plan->id }}"
                     class="plan-card relative rounded-2xl bg-white border
                            {{ $isSelected ? $c['border'].' ring-2 '.$c['ring'].' '.$c['glow'].' selected' : 'border-slate-200' }}
                            p-7 cursor-pointer animate-fade-in-up delay-{{ ($index+1)*100 }} shadow-sm"
                     wire:click="selectPlan({{ $plan->id }})">

                    @if($isFeatured)
                        <div class="absolute -top-px left-1/2 -translate-x-1/2">
                            <div class="bg-gradient-to-r from-emerald-500 to-teal-400 text-white text-xs font-semibold px-4 py-1 rounded-b-lg mono tracking-widest">POPULAR</div>
                        </div>
                    @endif

                    @if($isCurrentPlan)
                        <div class="absolute top-5 right-5">
                            <span class="badge-current inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border {{ $c['badge'] }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>Active
                            </span>
                        </div>
                    @endif

                    <div class="mb-6">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center mb-4">
                            @if($index===0)<x-icon name="light-bulb" class="w-5 h-5 {{ $c['accent'] }}" />
                            @elseif($index===1)<x-icon name="sparkles" class="w-5 h-5 {{ $c['accent'] }}" />
                            @else<x-icon name="cube" class="w-5 h-5 {{ $c['accent'] }}" />@endif
                        </div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ $plan->name }}</h3>
                        <p class="text-sm text-slate-400 mt-1 mono">{{ $plan->slug }}</p>
                    </div>

                    <div class="mb-7">
                        <div class="flex items-end gap-1.5">
                            <span class="text-4xl font-semibold text-slate-900">K{{ number_format($plan->price, 2) }}</span>
                            <span class="text-slate-400 text-sm mb-1.5">/mo</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Base price · discounts apply at checkout</p>
                    </div>

                    <div class="border-t border-slate-100 mb-6"></div>

                    @if($plan->description)
                        <p class="text-sm text-slate-400 mb-5">{{ $plan->description }}</p>
                    @endif

                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center gap-3 text-sm text-slate-600">
                            <x-icon name="users" class="w-4 h-4 flex-shrink-0 {{ $c['check'] }}" />
                            @if($plan->max_members)
                                <span>Up to <strong class="text-slate-900">{{ number_format($plan->max_members) }}</strong> members</span>
                            @else
                                <span><strong class="text-slate-900">Unlimited</strong> members</span>
                            @endif
                        </li>
                        <li class="flex items-center gap-3 text-sm text-slate-600">
                            <x-icon name="check" class="w-4 h-4 flex-shrink-0 {{ $c['check'] }}" />
                            <span>Member & attendance tracking</span>
                        </li>
                        <li class="flex items-center gap-3 text-sm text-slate-600">
                            <x-icon name="check" class="w-4 h-4 flex-shrink-0 {{ $c['check'] }}" />
                            <span>Tithes, offerings & pledges</span>
                        </li>
                        <li class="flex items-center gap-3 text-sm text-slate-600">
                            <x-icon name="check" class="w-4 h-4 flex-shrink-0 {{ $c['check'] }}" />
                            <span>Expense management</span>
                        </li>
                        @if($index >= 1)
                        <li class="flex items-center gap-3 text-sm text-slate-600">
                            <x-icon name="check" class="w-4 h-4 flex-shrink-0 {{ $c['check'] }}" />
                            <span>Advanced reports & exports</span>
                        </li>
                        @endif
                        @if($index >= 2)
                        <li class="flex items-center gap-3 text-sm text-slate-600">
                            <x-icon name="check" class="w-4 h-4 flex-shrink-0 {{ $c['check'] }}" />
                            <span>Priority support & SLA</span>
                        </li>
                        @endif
                    </ul>

                    @if($isCurrentPlan)
                        <button disabled class="w-full py-2.5 rounded-xl bg-slate-100 border border-slate-200 text-slate-400 text-sm font-medium cursor-default">
                            Current Plan
                        </button>
                    @else
                        <button wire:click.stop="selectPlan({{ $plan->id }})"
                                class="w-full py-2.5 rounded-xl {{ $c['btn'] }} text-white text-sm font-medium transition-colors duration-150">
                            {{ $isSelected ? 'Selected ✓' : 'Select Plan' }}
                        </button>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- ── Duration Selector ────────────────────────────────────────── --}}
        @if($selectedPlan && $selectedPlan !== $currentPlan)
        <div class="rounded-2xl bg-white border border-slate-200 p-6 mb-6 animate-fade-in-up shadow-sm">
            <p class="text-xs text-slate-400 mono tracking-widest uppercase mb-4">Subscription Duration</p>
            <div class="flex flex-wrap gap-3">
                @foreach($monthOptions as $m => $opt)
                    <button
                        wire:click="$set('months', {{ $m }})"
                        class="month-pill {{ $months == $m ? 'active' : '' }}">
                        {{ $opt['label'] }}
                        @if($opt['discount'] > 0)
                            <span class="discount-tag">-{{ $opt['discount'] }}%</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- ── Price Summary Bar + Proceed CTA ────────────────────────── --}}
        <div class="summary-bar rounded-2xl p-6 mb-8 animate-fade-in-up">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap gap-6">
                    <div>
                        <p class="text-xs text-slate-400 mono uppercase tracking-widest mb-1">Plan</p>
                        <p class="text-slate-900 font-semibold">{{ $this->selectedPlanModel?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 mono uppercase tracking-widest mb-1">Duration</p>
                        <p class="text-slate-900 font-semibold">{{ $monthOptions[$months]['label'] }}</p>
                    </div>
                    @if(($monthOptions[$months]['discount'] ?? 0) > 0)
                    <div>
                        <p class="text-xs text-slate-400 mono uppercase tracking-widest mb-1">Discount</p>
                        <p class="text-emerald-600 font-semibold">{{ $monthOptions[$months]['discount'] }}% off</p>
                    </div>
                    @endif
                    <div>
                        <p class="text-xs text-slate-400 mono uppercase tracking-widest mb-1">Per Month</p>
                        <p class="text-slate-900 font-semibold">K{{ number_format($this->monthlyPrice, 2) }}</p>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-2">
                    <div class="text-right">
                        <p class="text-xs text-slate-400 mono uppercase tracking-widest mb-1">Total</p>
                        <p class="text-3xl font-bold text-emerald-600">K{{ number_format($this->totalAmount, 2) }}</p>
                    </div>
                    <button wire:click="proceedToPayment"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 disabled:opacity-60 text-white text-sm font-semibold transition-colors shadow-sm">
                        <span wire:loading.remove wire:target="proceedToPayment">
                            <x-icon name="lock-closed" class="w-4 h-4 inline mr-1" />
                            Proceed to Payment
                        </span>
                        <span wire:loading wire:target="proceedToPayment">Preparing&hellip;</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Pay Offline Toggle ───────────────────────────────────────── --}}
        <div class="mb-6 flex items-center gap-3">
            <div class="flex-1 border-t border-slate-200"></div>
            <span class="text-xs text-slate-400 mono uppercase tracking-widest">or</span>
            <div class="flex-1 border-t border-slate-200"></div>
        </div>

        @if(!$showOfflineForm)
        <div class="text-center mb-8">
            <button wire:click="$set('showOfflineForm', true)"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-sm font-medium transition-colors shadow-sm">
                <x-icon name="banknotes" class="w-4 h-4" />
                Pay via Bank Transfer / Offline
            </button>
            <p class="mt-2 text-xs text-slate-400">Upload your proof of payment and our team will activate your plan within 24 hours.</p>
        </div>
        @else
        <div class="rounded-2xl bg-white border border-amber-200 p-6 mb-8 shadow-sm animate-fade-in-up">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center flex-shrink-0">
                    <x-icon name="banknotes" class="w-5 h-5 text-amber-600" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Offline / Bank Transfer Payment</h3>
                    <p class="text-xs text-slate-400">Upload proof of your payment and our team will review and activate your plan.</p>
                </div>
            </div>

            <div class="rounded-xl bg-amber-50 border border-amber-100 p-4 mb-5 text-sm text-amber-800">
                <p class="font-semibold mb-1">Payment Summary</p>
                <p>Plan: <strong>{{ $this->selectedPlanModel?->name }}</strong> &nbsp;·&nbsp; Duration: <strong>{{ $monthOptions[$months]['label'] }}</strong> &nbsp;·&nbsp; Total: <strong>K{{ number_format($this->totalAmount, 2) }}</strong></p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Transaction Reference <span class="text-slate-400 font-normal">(optional)</span></flux:label>
                    <flux:input wire:model="offlineReference" placeholder="e.g. bank ref, mobile money receipt no." />
                    <flux:error name="offlineReference" />
                </flux:field>

                <flux:field>
                    <flux:label>Proof of Payment <span class="text-slate-400 font-normal">(JPG, PNG or PDF, max 5MB)</span></flux:label>
                    <input type="file" wire:model="offlineProof" accept=".jpg,.jpeg,.png,.pdf"
                           class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-amber-700 hover:file:bg-amber-100" />
                    <div wire:loading wire:target="offlineProof" class="mt-1 text-xs text-slate-400">Uploading…</div>
                    <flux:error name="offlineProof" />
                </flux:field>
            </div>

            <flux:field class="mt-4">
                <flux:label>Notes <span class="text-slate-400 font-normal">(optional)</span></flux:label>
                <flux:textarea wire:model="offlineNotes" rows="2" placeholder="Any additional details about your payment…" />
                <flux:error name="offlineNotes" />
            </flux:field>

            <div class="mt-5 flex gap-3">
                <flux:button wire:click="submitOfflinePayment" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submitOfflinePayment">Submit for Review</span>
                    <span wire:loading wire:target="submitOfflinePayment">Submitting…</span>
                </flux:button>
                <flux:button wire:click="$set('showOfflineForm', false)" variant="ghost">Cancel</flux:button>
            </div>
        </div>
        @endif

        @endif

        {{-- ── Current Plan Summary ─────────────────────────────────────── --}}
        @if($currentPlan)
        <div class="rounded-2xl bg-white border border-slate-200 p-6 mb-8 shadow-sm">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-center">
                        <x-icon name="check-circle" class="w-5 h-5 text-emerald-500" />
                    </div>
                    <div>
                        <p class="text-sm text-slate-400">Current active plan</p>
                        <p class="font-semibold text-slate-900">{{ $this->plans->firstWhere('id', $currentPlan)?->name ?? '—' }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <a href="#" class="text-sm text-slate-400 hover:text-slate-700 transition-colors">View invoices</a>
                    <span class="text-slate-300">|</span>
                    <a href="#" class="text-sm text-red-400 hover:text-red-500 transition-colors">Cancel subscription</a>
                </div>
            </div>
        </div>
        @endif

        {{-- ── Offline Payment Submission History ──────────────────────── --}}
        @if($this->myOfflineRequests->count() > 0)
        <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-4 mono uppercase tracking-widest">Offline Payment Submissions</h3>
            <div class="space-y-3">
                @foreach($this->myOfflineRequests as $req)
                <div class="flex items-center justify-between gap-4 py-2 border-b border-slate-100 last:border-0">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                            <x-icon name="document-text" class="w-4 h-4 text-slate-500" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ $req->plan?->name }} &middot; {{ $req->months }} month(s)</p>
                            <p class="text-xs text-slate-400">{{ $req->created_at->format('M d, Y') }} &middot; K{{ number_format($req->amount, 2) }}</p>
                        </div>
                    </div>
                    @if($req->status === 'approved')
                        <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">Approved</span>
                    @elseif($req->status === 'rejected')
                        <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700" title="{{ $req->rejection_reason }}">Rejected</span>
                    @else
                        <span class="inline-flex rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-semibold text-yellow-700">Pending Review</span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>

    {{-- Lenco Pay script lives inside the component div so @this compiles correctly --}}
    <script src="https://pay.lenco.co/js/v1/inline.js"></script>

    <script>
        Livewire.on('launchLencoPay', ([payload]) => {
                const { amount, email, firstName, lastName } = payload;
                console.log(payload);
                if (!amount || amount <= 0) {
                    alert('Invalid payment amount. Please select a plan and try again.');
                    return;
                }

                LencoPay.getPaid({
                    key:       'pub-071f354dc65dbbb786644b8aa7f0fd601948782e79bf5cbf',
                    reference: 'ref-' + Date.now(),
                    email:     email,
                    amount:    amount,
                    currency:  'ZMW',
                    channels:  ['card', 'mobile-money'],
                    customer: {
                        firstName: firstName || 'Subscriber',
                        lastName:  lastName  || '',
                        phone:     '',
                    },

                    onSuccess: function (response) {
                        // Hand the verified reference back to Livewire to create the subscription
                       $wire.completeSubscription(response.reference);
                    },

                    onClose: function () {
                        // User dismissed — no action needed, they can try again
                    },

                    onConfirmationPending: function () {
                        alert('Your payment is pending confirmation. We will activate your plan once verified.');
                    },
                });
            });
    </script>
    <x-spinner/>
    <x-notifications />
</div>
