<?php

use App\Models\OrganizationPlan;
use Livewire\Component;

new class extends Component
{
    public ?OrganizationPlan $expiredPlan = null;

    public function mount(): void
    {
        $orgId = auth()->user()?->myOrganization?->organization_id;

        if ($orgId) {
            $this->expiredPlan = OrganizationPlan::with('plan')
                ->where('organization_id', $orgId)
                ->where('is_active', true)
                ->latest('end_date')
                ->first();
        }
    }
};
?>

<div class="min-h-screen bg-slate-50 flex items-center justify-center px-4 py-16"
     style="font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif;">

    <div class="max-w-lg w-full">

        {{-- Icon --}}
        <div class="flex justify-center mb-6">
            <div class="w-20 h-20 rounded-full bg-red-50 border border-red-200 flex items-center justify-center">
                <x-icon name="clock" class="w-10 h-10 text-red-400" />
            </div>
        </div>

        {{-- Heading --}}
        <div class="text-center mb-8">
            <h1 class="text-3xl font-semibold text-slate-900 mb-3">Your subscription has expired</h1>
            <p class="text-slate-500">
                Your access has been temporarily paused. Renew your plan to continue using all features.
            </p>
        </div>

        {{-- Expired plan details --}}
        @if($expiredPlan?->plan)
        <div class="rounded-2xl bg-white border border-red-100 p-6 mb-6 shadow-sm">
            <p class="text-xs text-slate-400 uppercase tracking-widest font-medium mb-4">Expired Plan</p>
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <p class="text-lg font-semibold text-slate-900">{{ $expiredPlan->plan->name }}</p>
                    <p class="text-sm text-slate-400">K{{ number_format($expiredPlan->plan->price, 2) }} / month</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-400 mb-1">Expired on</p>
                    <p class="text-sm font-semibold text-red-500">
                        {{ $expiredPlan->end_date?->format('M d, Y') ?? '—' }}
                    </p>
                </div>
            </div>
        </div>
        @endif

        {{-- CTA --}}
        <div class="flex flex-col gap-3">
            <a href="{{ route('subscription.plans') }}"
               class="flex items-center justify-center gap-2 w-full py-3 px-6 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold transition-colors shadow-sm">
                <x-icon name="arrow-path" class="w-4 h-4" />
                Renew Subscription
            </a>
            <a href="{{ route('create.organization') }}"
               class="flex items-center justify-center w-full py-3 px-6 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 text-sm font-medium transition-colors">
                Go to Organization Settings
            </a>
        </div>

        {{-- Support note --}}
        <p class="text-center text-xs text-slate-400 mt-6">
            Need help? Contact support or upload proof of payment on the plans page.
        </p>

    </div>
</div>
