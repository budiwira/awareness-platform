<?php

use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use App\Models\UserModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->tenantAdmin()->create(['tenant_id' => $this->tenant->id]);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->module = TrainingModule::create([
        'title' => 'Modul 1', 'content' => 'Konten', 'duration_minutes' => 10,
        'is_active' => true, 'status' => 'published',
    ]);
    $this->package = Package::create([
        'name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 1500,
        'max_users' => 100, 'includes_all_modules' => true,
        'features' => ['training', 'phishing'],
    ]);
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'package_id' => $this->package->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
});

test('tenant admin cannot open tenant module access page', function () {
    $this->actingAs($this->admin)
        ->get("/tenant/users/{$this->user->id}/access")
        ->assertNotFound();
});

test('tenant admin cannot post tenant module access override', function () {
    $this->actingAs($this->admin)
        ->post("/tenant/users/{$this->user->id}/access", [
            'module_ids' => [$this->module->id],
            'is_allowed' => false,
        ])
        ->assertNotFound();

    $this->assertDatabaseMissing('user_module_access', [
        'user_id' => $this->user->id,
        'training_module_id' => $this->module->id,
    ]);
});

test('regular learner cannot access tenant module access endpoints', function () {
    $this->actingAs($this->user)
        ->get("/tenant/users/{$this->user->id}/access")
        ->assertNotFound();

    $this->actingAs($this->user)
        ->post("/tenant/users/{$this->user->id}/access", [
            'module_ids' => [$this->module->id],
            'is_allowed' => false,
        ])
        ->assertNotFound();
});

test('super admin platform override remains functional', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)->post(route('platform.tenants.user-access.update', $this->tenant), [
        'user_id' => $this->user->id,
        'module_ids' => [$this->module->id],
        'is_allowed' => false,
    ])->assertOk();

    $this->assertDatabaseHas('user_module_access', [
        'user_id' => $this->user->id,
        'training_module_id' => $this->module->id,
        'is_allowed' => false,
        'granted_by' => $superAdmin->id,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.module_access_revoked',
        'subject_id' => $this->module->id,
        'actor_user_id' => $superAdmin->id,
    ]);
});

test('existing module overrides are preserved when tenant routes are unavailable', function () {
    $override = UserModuleAccess::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'training_module_id' => $this->module->id,
        'is_allowed' => false,
    ]);

    $this->actingAs($this->admin)
        ->get("/tenant/users/{$this->user->id}/access")
        ->assertNotFound();

    $this->assertDatabaseHas('user_module_access', ['id' => $override->id, 'is_allowed' => false]);
});

test('tenant admin post cannot alter an existing platform override', function () {
    $override = UserModuleAccess::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'training_module_id' => $this->module->id,
        'is_allowed' => false,
    ]);

    $this->actingAs($this->admin)
        ->post("/tenant/users/{$this->user->id}/access", [
            'module_ids' => [$this->module->id],
            'is_allowed' => true,
        ])
        ->assertNotFound();

    expect($override->fresh()->is_allowed)->toBeFalse();
});

test('learner post cannot alter an existing platform override', function () {
    $override = UserModuleAccess::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'training_module_id' => $this->module->id,
        'is_allowed' => false,
    ]);

    $this->actingAs($this->user)
        ->post("/tenant/users/{$this->user->id}/access", [
            'module_ids' => [$this->module->id],
            'is_allowed' => true,
        ])
        ->assertNotFound();

    expect($override->fresh()->is_allowed)->toBeFalse();
});

test('super admin can open platform user access page', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('platform.tenants.user-access.show', $this->tenant))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Tenants/UserAccess')
            ->where('tenant.id', $this->tenant->id)
        );
});

test('super admin can grant module access through platform route', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)->post(route('platform.tenants.user-access.update', $this->tenant), [
        'user_id' => $this->user->id,
        'module_ids' => [$this->module->id],
        'is_allowed' => true,
    ])->assertOk();

    $this->assertDatabaseHas('user_module_access', [
        'user_id' => $this->user->id,
        'training_module_id' => $this->module->id,
        'is_allowed' => true,
        'granted_by' => $superAdmin->id,
    ]);
});

test('platform override rejects a user from another tenant', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

    $this->actingAs($superAdmin)->post(route('platform.tenants.user-access.update', $this->tenant), [
        'user_id' => $otherUser->id,
        'module_ids' => [$this->module->id],
        'is_allowed' => false,
    ])->assertNotFound();

    $this->assertDatabaseMissing('user_module_access', [
        'user_id' => $otherUser->id,
        'training_module_id' => $this->module->id,
    ]);
});
