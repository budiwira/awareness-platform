<?php

use App\Models\User;

test('guest is redirected to login from dashboard', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('super admin lands on platform dashboard', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get('/dashboard')
        ->assertRedirect(route('platform.dashboard'));

    $this->actingAs($user)->get(route('platform.dashboard'))->assertOk();
});

test('tenant admin lands on tenant dashboard', function () {
    $user = User::factory()->tenantAdmin()->create();

    $this->actingAs($user)->get('/dashboard')
        ->assertRedirect(route('tenant.dashboard'));

    $this->actingAs($user)->get(route('tenant.dashboard'))->assertOk();
});

test('user lands on user dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')
        ->assertRedirect(route('user.dashboard'));

    $this->actingAs($user)->get(route('user.dashboard'))->assertOk();
});

test('tenant admin cannot access platform dashboard', function () {
    $user = User::factory()->tenantAdmin()->create();

    $this->actingAs($user)->get(route('platform.dashboard'))->assertForbidden();
});

test('user cannot access tenant dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('tenant.dashboard'))->assertForbidden();
});

test('user cannot access platform dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('platform.dashboard'))->assertForbidden();
});

test('super admin cannot access tenant dashboard', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get(route('tenant.dashboard'))->assertForbidden();
});

test('inactive user is denied dashboard access', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->actingAs($user)->get(route('user.dashboard'))->assertForbidden();
});
