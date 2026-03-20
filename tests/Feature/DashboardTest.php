<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('admin can visit the dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Admin Dashboard');
});

test('admin dashboard shows admin-specific content', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Admin Dashboard');
    $response->assertSee('Total Users');
    $response->assertSee('Organizations');
    $response->assertSee('Active Subscriptions');
    $response->assertSee('Total Revenue');
});
