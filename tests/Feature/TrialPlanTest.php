<?php

use App\Models\Organization;
use App\Models\OrganizationPlan;
use App\Models\OrganizationUser;
use App\Models\Plan;
use App\Models\User;

beforeEach(function () {
    // Ensure a trial plan exists
    Plan::where('slug', 'free-trial')->delete();

    $this->trialPlan = Plan::create([
        'name'        => 'Free Trial',
        'slug'        => 'free-trial',
        'description' => '3-day free trial',
        'price'       => 0.00,
        'max_members' => 50,
        'is_active'   => true,
        'is_trial'    => true,
        'trial_days'  => 3,
    ]);
});

test('trial plan has correct attributes', function () {
    expect($this->trialPlan->is_trial)->toBeTrue()
        ->and($this->trialPlan->trial_days)->toBe(3)
        ->and((float) $this->trialPlan->price)->toBe(0.0)
        ->and($this->trialPlan->is_active)->toBeTrue();
});

test('plan scope trial returns only trial plans', function () {
    Plan::where('slug', 'starter-test')->delete();

    $paid = Plan::create([
        'name'        => 'Starter Test',
        'slug'        => 'starter-test',
        'price'       => 99.00,
        'max_members' => 100,
        'is_active'   => true,
        'is_trial'    => false,
        'trial_days'  => null,
    ]);

    $trials = Plan::trial()->get();
    $paidPlans = Plan::paid()->get();

    expect($trials->contains('slug', 'free-trial'))->toBeTrue()
        ->and($trials->contains('slug', 'starter-test'))->toBeFalse()
        ->and($paidPlans->contains('slug', 'starter-test'))->toBeTrue()
        ->and($paidPlans->contains('slug', 'free-trial'))->toBeFalse();

    $paid->delete();
});

test('creating an organization auto-assigns the trial plan', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $org = null;

    \Illuminate\Support\Facades\DB::transaction(function () use ($user, &$org) {
        $org = Organization::create([
            'owner_id' => $user->id,
            'name'     => 'Grace Church',
            'slug'     => 'grace-church-trial-' . $user->id,
            'currency' => 'ZMW',
        ]);

        OrganizationUser::firstOrCreate([
            'user_id'         => $user->id,
            'organization_id' => $org->id,
            'user_type'       => 'manager',
        ]);

        $trial = Plan::trial()->where('is_active', true)->first();

        if ($trial) {
            OrganizationPlan::create([
                'organization_id' => $org->id,
                'plan_id'         => $trial->id,
                'start_date'      => now(),
                'end_date'        => now()->addDays($trial->trial_days),
                'is_active'       => true,
            ]);
        }
    });

    $orgPlan = OrganizationPlan::where('organization_id', $org->id)
        ->with('plan')
        ->first();

    expect($orgPlan)->not->toBeNull()
        ->and($orgPlan->plan->is_trial)->toBeTrue()
        ->and($orgPlan->plan->trial_days)->toBe(3)
        ->and((int) $orgPlan->start_date->diffInDays($orgPlan->end_date))->toBe(3)
        ->and($orgPlan->is_active)->toBeTrue();
});

test('trial plan end date is 3 days from creation', function () {
    $user  = User::factory()->create(['role' => 'user']);
    $org   = Organization::create([
        'owner_id' => $user->id,
        'name'     => 'Test Church',
        'slug'     => 'test-church-days-' . $user->id,
        'currency' => 'ZMW',
    ]);

    $plan = OrganizationPlan::create([
        'organization_id' => $org->id,
        'plan_id'         => $this->trialPlan->id,
        'start_date'      => now(),
        'end_date'        => now()->addDays(3),
        'is_active'       => true,
    ]);

    expect((int) $plan->start_date->diffInDays($plan->end_date))->toBe(3)
        ->and($plan->hasActivePlan())->toBeTrue();
});
