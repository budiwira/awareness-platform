<?php

use App\Models\Plan;
use App\Models\User;

test('super admin can create plan', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)
        ->post(route('platform.plans.store'), [
            'name' => 'Business',
            'price_monthly' => 2000,
            'max_users' => 50,
            'features' => ['training', 'ttx'],
        ])
        ->assertRedirect(route('platform.plans.index'));

    $this->assertDatabaseHas('plans', ['slug' => 'business', 'max_users' => 50]);
});

test('tenant admin cannot access plans', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->get(route('platform.plans.index'))->assertForbidden();
});