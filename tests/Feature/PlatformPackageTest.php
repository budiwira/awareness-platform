<?php

use App\Models\User;

test('super admin can create Package', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)
        ->post(route('platform.packages.store'), [
            'name' => 'Business',
            'price_monthly' => 2000,
            'max_users' => 50,
            'features' => ['training', 'ttx'],
        ])
        ->assertRedirect(route('platform.packages.index'));

    $this->assertDatabaseHas('packages', ['slug' => 'business', 'max_users' => 50]);
});

test('tenant admin cannot access packages', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->get(route('platform.packages.index'))->assertForbidden();
});
