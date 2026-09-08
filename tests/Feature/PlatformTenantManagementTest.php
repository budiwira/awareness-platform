<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('super admin lists tenants with user counts', function () {
    $super = User::factory()->superAdmin()->create();
    Tenant::factory()->create(['name' => 'Acme']);

    $this->actingAs($super)
        ->get(route('platform.tenants.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Tenants/Index')
            ->has('tenants', 1)
        );
});

test('super admin creates tenant; audit logged', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)
        ->post(route('platform.tenants.store'), ['name' => 'Gamma Corp'])
        ->assertRedirect(route('platform.tenants.index'));

    $this->assertDatabaseHas('tenants', [
        'name' => 'Gamma Corp',
        'slug' => 'gamma-corp',
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.created']);
});

test('duplicate name gets unique slug suffix', function () {
    $super = User::factory()->superAdmin()->create();
    Tenant::factory()->create(['slug' => 'gamma-corp']);

    $this->actingAs($super)
        ->post(route('platform.tenants.store'), ['name' => 'Gamma Corp'])
        ->assertRedirect();

    $this->assertDatabaseHas('tenants', ['slug' => 'gamma-corp-2']);
});

test('tenant admin cannot access platform tenants', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->get(route('platform.tenants.index'))->assertForbidden();
});
