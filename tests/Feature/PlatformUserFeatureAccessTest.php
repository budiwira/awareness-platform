<?php

use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserFeatureAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $this->tenant = Tenant::factory()->create();

    $this->superAdmin = User::factory()->create([
        'tenant_id' => null,
        'role' => 'super_admin',
    ]);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'user',
    ]);

    $this->package = Package::create([
        'name' => 'Enterprise',
        'slug' => 'enterprise',
        'price_monthly' => 5000,
        'max_users' => 1000,
        'includes_all_modules' => true,
        'features' => ['phishing', 'ctf'],
    ]);

    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'package_id' => $this->package->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
});

test('super admin can revoke entitled feature access for tenant user', function () {
    $response = $this->actingAs($this->superAdmin)->postJson(
        route('platform.tenants.user-access.update', $this->tenant),
        [
            'user_id' => $this->user->id,
            'feature_keys' => ['phishing'],
            'is_allowed' => false,
        ]
    );

    $response->assertOk();

    $this->assertDatabaseHas('user_feature_access', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'feature_key' => 'phishing',
        'is_allowed' => false,
    ]);
});

test('super admin cannot grant feature outside tenant package', function () {
    $response = $this->actingAs($this->superAdmin)->postJson(
        route('platform.tenants.user-access.update', $this->tenant),
        [
            'user_id' => $this->user->id,
            'feature_keys' => ['ttx'],
            'is_allowed' => true,
        ]
    );

    $response->assertStatus(422)
        ->assertJson([
            'error' => 'Fitur tidak termasuk dalam paket tenant',
            'invalid_feature_keys' => ['ttx'],
        ]);

    $this->assertDatabaseMissing('user_feature_access', [
        'user_id' => $this->user->id,
        'feature_key' => 'ttx',
    ]);
});

test('super admin user access page includes entitled features and override state', function () {
    UserFeatureAccess::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'feature_key' => 'phishing',
        'is_allowed' => false,
    ]);

    $response = $this->actingAs($this->superAdmin)->get(
        route('platform.tenants.user-access.show', $this->tenant)
    );

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Platform/Tenants/UserAccess')
        ->where('tenant.id', $this->tenant->id)
        ->has('users', 1)
        ->where('users.0.user_id', $this->user->id)
        ->has('users.0.features', 2)
        ->where('users.0.features.0.key', 'phishing')
        ->where('users.0.features.0.is_allowed', false)
        ->where('users.0.features.1.key', 'ctf')
        ->where('users.0.features.1.is_allowed', true)
    );
});
