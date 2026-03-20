<?php

use App\Exports\CollectionsExport;
use App\Exports\ExpensesExport;
use App\Exports\PledgesExport;
use App\Models\Organization;
use App\Models\OrganizationPlan;
use App\Models\OrganizationUser;
use App\Models\Plan;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    Excel::fake();

    $this->user = User::factory()->create(['role' => 'user']);

    $this->org = Organization::create([
        'owner_id' => $this->user->id,
        'name'     => 'Test Church',
        'slug'     => 'test-church',
    ]);

    $plan = Plan::create(['name' => 'Basic', 'slug' => 'basic', 'price' => 0]);

    OrganizationPlan::create([
        'organization_id' => $this->org->id,
        'plan_id'         => $plan->id,
        'start_date'      => now()->subDay(),
        'end_date'        => now()->addYear(),
        'is_active'       => true,
    ]);

    OrganizationUser::create([
        'user_id'         => $this->user->id,
        'organization_id' => $this->org->id,
        'user_type'       => 'manager',
    ]);

    $this->actingAs($this->user);
});

test('collections export class can be instantiated with sheets', function () {
    $export = new CollectionsExport($this->org->id, now()->format('Y'), '');
    $sheets = $export->sheets();

    expect($sheets)->toBeArray()->not->toBeEmpty();
});

test('expenses export class produces two sheets', function () {
    $export = new ExpensesExport($this->org->id, now()->format('Y'), '', '', '');
    $sheets = $export->sheets();

    expect($sheets)->toBeArray()->toHaveCount(2);
});

test('pledges export class has correct headings', function () {
    $export = new PledgesExport($this->org->id, now()->format('Y'), null, null, '');

    expect($export->title())->toBe('Pledges');
    expect($export->headings())->toHaveCount(8);
    expect($export->collection())->toBeEmpty();
});
