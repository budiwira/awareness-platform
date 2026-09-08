<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('tenant admin lists only own tenant users', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenantA->id, 'name' => 'Admin A']);
    User::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Member A']);
    User::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Member B']);

    $response = $this->actingAs($admin)->get(route('tenant.users.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Users/Index')
            ->has('users', 2)
        );

    $html = $response->getContent();
    expect($html)->toContain('Member A')->not->toContain('Member B');
});

test('tenant admin creates user into own tenant; injected tenant_id ignored', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenantA->id]);

    $this->actingAs($admin)->post(route('tenant.users.store'), [
        'name' => 'New Member',
        'email' => 'new@member.local',
        'role' => 'user',
        'tenant_id' => $tenantB->id, // percobaan injeksi — harus diabaikan
    ])->assertRedirect(route('tenant.users.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'new@member.local',
        'tenant_id' => $tenantA->id,
    ]);

    $this->assertDatabaseHas('audit_logs', ['action' => 'user.created']);
});

test('tenant admin cannot create super admin', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->post(route('tenant.users.store'), [
        'name' => 'Evil',
        'email' => 'evil@local',
        'role' => 'super_admin',
    ])->assertSessionHasErrors('role');
});

test('tenant admin cannot update user from another tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenantA->id]);
    $victim = User::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($admin)->patch(route('tenant.users.update', $victim), [
        'name' => 'Hacked',
        'email' => $victim->email,
        'role' => 'user',
        'is_active' => false,
    ])->assertForbidden();
});

test('tenant admin cannot change own role', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->patch(route('tenant.users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => 'user',
        'is_active' => true,
    ])->assertForbidden();
});

test('tenant admin cannot disable own account', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->patch(route('tenant.users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => $admin->role->value,
        'is_active' => false,
    ])->assertForbidden();
});

test('regular user cannot access tenant user management', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('tenant.users.index'))->assertForbidden();
});

test('role change and disable write audit logs', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $member = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)->patch(route('tenant.users.update', $member), [
        'name' => $member->name,
        'email' => $member->email,
        'role' => 'tenant_admin',
        'is_active' => false,
    ])->assertRedirect(route('tenant.users.index'));

    $this->assertDatabaseHas('audit_logs', ['action' => 'user.role_changed']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.disabled']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated']);
});
