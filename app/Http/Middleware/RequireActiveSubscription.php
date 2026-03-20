<?php

namespace App\Http\Middleware;

use App\Models\OrganizationPlan;
use Closure;
use Illuminate\Http\Request;
use App\Models\OrganizationUser;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(!$request->user())
        {
            return $next($request);
        }

        if(auth()->check() && auth()->user()->role == 'admin'){
            return $next($request);
        }

        $subscription = $this->getActiveSubscription($request);

        if(!$subscription)
            {
                return $this->handleNoSubscription($request);
            }

        if(!$subscription->hasActivePlan())
            {
                return $this->handleExpiredSubscription($request,$subscription);
            }

        $request->merge(['active_subscription' => $subscription]);

        return $next($request);
    }

    public function getActiveSubscription(Request $request): OrganizationPlan|null
    {
        $currentOrgId = session('current_org_id');

        // Auto-initialise session on first request — prefer highest-privilege org
        if (! $currentOrgId) {
            $orgUser = OrganizationUser::where('user_id', $request->user()->id)
                ->orderByRaw("FIELD(branch_role, 'owner', 'manager', 'member')")
                ->first();

            if (! $orgUser) {
                return null;
            }

            $currentOrgId = $orgUser->organization_id;
            session(['current_org_id' => $currentOrgId]);
        }

        return OrganizationPlan::with('plan')
            ->where('organization_id', $currentOrgId)
            ->active()
            ->latest('end_date')
            ->first();
    }

    public function handleNoSubscription(Request $request)
    {
        if($request->expectsJson())
        {
            return response()->json([
                'message' => 'A subscription is required to access this resource.',
                'code'    => 'NO_SUBSCRIPTION',
            ], Response::HTTP_FORBIDDEN);
        }

        return redirect()
        ->route('subscription.plans')
        ->with('error','You need an active subscription to access this page. Please choose a plan to continue.');
    }

    private function handleExpiredSubscription(Request $request, OrganizationPlan $subscription): Response
    {
        $organization_id = OrganizationUser::where('user_id',$request->user()->id)->first();
        if ($request->expectsJson()) {
            return response()->json([
                'message'    => 'Your subscription has expired.',
                'code'       => 'SUBSCRIPTION_EXPIRED',
                'expired_at' => $subscription->where('organization_id',$organization_id)->ends_date->toIso8601String(),
                'plan'       => $subscription->where('organization_id',$organization_id)->plan?->name,
            ], Response::HTTP_FORBIDDEN);
        }

        return redirect()
            ->route('expired.plans')
            ->with('expired_subscription', $subscription);
    }
}
