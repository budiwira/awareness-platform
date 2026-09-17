<?php

use App\Models\Package;
use App\Models\Subscription;
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

test('tenant listing preserves recorded subscription context and isolated user counts', function () {
    $super = User::factory()->superAdmin()->create();
    $first = Tenant::factory()->create(['name' => 'Alpha']);
    $second = Tenant::factory()->create(['name' => 'Beta', 'status' => 'inactive']);
    User::factory()->count(2)->create(['tenant_id' => $first->id]);
    User::factory()->create(['tenant_id' => $second->id]);
    $package = Package::create(['name' => 'Recorded', 'slug' => 'recorded', 'price_monthly' => 100, 'max_users' => 25, 'is_active' => true]);
    $subscription = Subscription::create([
        'tenant_id' => $first->id, 'package_id' => $package->id, 'status' => 'active',
        'started_at' => now()->subMonths(2), 'ends_at' => now()->subMonth(),
    ]);

    $this->actingAs($super)->get(route('platform.tenants.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Platform/Tenants/Index')
            ->where('tenants.0.id', $first->id)->where('tenants.0.users_count', 2)
            ->where('tenants.0.current_package', 'Recorded')
            ->where('tenants.0.subscriptions.0.id', $subscription->id)
            ->where('tenants.1.id', $second->id)->where('tenants.1.users_count', 1)
            ->where('tenants.1.status', 'inactive')->where('tenants.1.current_package', null)
            ->missing('tenants.0.users')
        );
});

test('non platform operators cannot manage either tenant through platform endpoints', function (string $role) {
    $own = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $actor = $role === 'inactive_super'
        ? User::factory()->superAdmin()->create(['is_active' => false])
        : User::factory()->create(['tenant_id' => $own->id, 'role' => $role]);
    $member = User::factory()->create(['tenant_id' => $other->id]);
    $this->actingAs($actor)->get(route('platform.tenants.index'))->assertForbidden();
    $this->post(route('platform.tenants.store'), ['name' => 'Forbidden tenant'])->assertForbidden();
    foreach ([$own, $other] as $tenant) {
        $this->get(route('platform.tenants.user-access.show', $tenant))->assertForbidden();
        $this->post(route('platform.tenants.user-access.update', $tenant), ['user_id' => $member->id, 'feature_keys' => ['training'], 'is_allowed' => false])->assertForbidden();
        $this->post(route('platform.tenants.set-package'), ['tenant_id' => $tenant->id, 'package_id' => 1])->assertForbidden();
    }
    $this->assertDatabaseMissing('tenants', ['name' => 'Forbidden tenant']);
    $this->assertDatabaseCount('subscriptions', 0);
    $this->assertDatabaseCount('user_feature_access', 0);
})->with(['tenant_admin', 'user', 'inactive_super']);

test('tenant creation ignores injected status slug and role', function () {
    $super = User::factory()->superAdmin()->create();
    $this->actingAs($super)->post(route('platform.tenants.store'), [
        'name' => 'Safe organization', 'slug' => 'injected', 'status' => 'inactive', 'role' => 'super_admin',
    ])->assertRedirect(route('platform.tenants.index'));
    $this->assertDatabaseHas('tenants', ['name' => 'Safe organization', 'slug' => 'safe-organization', 'status' => 'active']);
});
