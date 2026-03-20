<?php

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Payments;
use App\Models\PaymentCategory;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => 'user']);

    $this->org = Organization::create([
        'owner_id' => $this->owner->id,
        'name'     => 'Grace Church',
        'slug'     => 'grace-church',
        'currency' => 'NGN',
    ]);

    OrganizationUser::create([
        'user_id'         => $this->owner->id,
        'organization_id' => $this->org->id,
        'user_type'       => 'manager',
    ]);

    $category = PaymentCategory::create([
        'organization_id' => $this->org->id,
        'name'            => 'Tithe',
        'is_active'       => true,
    ]);

    $this->payment = Payments::create([
        'organization_id' => $this->org->id,
        'user_id'         => $this->owner->id,
        'name'            => 'John Doe',
        'amount'          => 5000.00,
        'category_id'     => $category->id,
        'payment_method'  => 'cash',
        'donation_date'   => now()->toDateString(),
    ]);
});

test('authenticated org member can view payment receipt as pdf', function () {
    $this->actingAs($this->owner);

    $response = $this->get(route('payment.receipt', $this->payment->id));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect(strlen($response->content()))->toBeGreaterThan(100);
});

test('unauthorized user cannot view receipt from another org', function () {
    $otherUser = User::factory()->create(['role' => 'user']);

    $otherOrg = Organization::create([
        'owner_id' => $otherUser->id,
        'name'     => 'Other Church',
        'slug'     => 'other-church',
    ]);

    OrganizationUser::create([
        'user_id'         => $otherUser->id,
        'organization_id' => $otherOrg->id,
        'user_type'       => 'manager',
    ]);

    $this->actingAs($otherUser);

    $response = $this->get(route('payment.receipt', $this->payment->id));

    $response->assertForbidden();
});

test('guest cannot view payment receipt', function () {
    $response = $this->get(route('payment.receipt', $this->payment->id));

    $response->assertRedirect(route('login'));
});

test('format_currency helper formats amounts correctly', function () {
    expect(format_currency(1250.00, 'ZMW'))->toBe('ZK 1,250.00');
    expect(format_currency(1250.00, 'NGN'))->toBe('₦1,250.00');
    expect(format_currency(1250.00, 'USD'))->toBe('$1,250.00');
    expect(format_currency(1250.00, 'GBP'))->toBe('£1,250.00');
});
