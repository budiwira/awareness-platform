<?php

use App\Models\Tenant;
use App\Models\User;

test('end-to-end: tenant admin login lands on tenant dashboard', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

    $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))->assertRedirect(route('tenant.dashboard'));

    $this->get(route('tenant.dashboard'))->assertOk();
});

test('end-to-end: super admin login lands on platform dashboard', function () {
    $super = User::factory()->superAdmin()->create();

    $this->post('/login', [
        'email' => $super->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->get(route('platform.dashboard'))->assertOk();
});