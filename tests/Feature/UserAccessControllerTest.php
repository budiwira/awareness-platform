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
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant_admin']);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']);
    $this->module1 = TrainingModule::create(['title' => 'Modul 1', 'content' => 'Konten', 'duration_minutes' => 10, 'is_active' => true, 'status' => 'published']);
    $this->module2 = TrainingModule::create(['title' => 'Modul 2', 'content' => 'Konten', 'duration_minutes' => 10, 'is_active' => true, 'status' => 'published']);
    $this->package = Package::create([
        'name' => 'Pro',
        'slug' => 'pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'includes_all_modules' => true,
        'features' => ['training', 'phishing'],
    ]);
    $this->subscription = Subscription::create([
        'tenant_id' => $this->tenant->id,
        'package_id' => $this->package->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
});

test('tenant admin can grant module access via HTTP', function () {
    $response = $this->actingAs($this->admin)->post(route('tenant.users.access.update', $this->user), [
        'module_ids' => [$this->module1->id],
        'is_allowed' => true,
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('user_module_access', [
        'user_id' => $this->user->id,
        'training_module_id' => $this->module1->id,
        'is_allowed' => true,
    ]);
});

test('tenant admin can revoke module access via HTTP', function () {
    UserModuleAccess::create([
        'user_id' => $this->user->id,
        'training_module_id' => $this->module1->id,
        'tenant_id' => $this->tenant->id,
        'is_allowed' => true,
    ]);

    $response = $this->actingAs($this->admin)->post(route('tenant.users.access.update', $this->user), [
        'module_ids' => [$this->module1->id],
        'is_allowed' => false,
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('user_module_access', [
        'user_id' => $this->user->id,
        'training_module_id' => $this->module1->id,
        'is_allowed' => false,
    ]);
});

test('tenant admin cannot modify user from other tenant (403)', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

    $response = $this->actingAs($this->admin)->post(route('tenant.users.access.update', $otherUser), [
        'module_ids' => [$this->module1->id],
        'is_allowed' => true,
    ]);

    $response->assertForbidden();
});

test('regular user cannot access endpoint (403)', function () {
    $response = $this->actingAs($this->user)->post(route('tenant.users.access.update', $this->user), [
        'module_ids' => [$this->module1->id],
        'is_allowed' => true,
    ]);

    $response->assertForbidden();
});

test('validation: module_id not in package returns 422', function () {
    $this->package->update(['includes_all_modules' => false]);
    $this->package->modules()->attach([$this->module1->id]);

    $response = $this->actingAs($this->admin)->post(route('tenant.users.access.update', $this->user), [
        'module_ids' => [$this->module2->id],
        'is_allowed' => true,
    ]);

    $response->assertStatus(422);
    $response->assertJson(['error' => 'Modul tidak termasuk dalam paket tenant']);
});

test('super admin can override user access', function () {
    $superAdmin = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);

    $response = $this->actingAs($superAdmin)->post(route('platform.tenants.user-access.update', $this->tenant), [
        'user_id' => $this->user->id,
        'module_ids' => [$this->module1->id],
        'is_allowed' => true,
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('user_module_access', [
        'user_id' => $this->user->id,
        'training_module_id' => $this->module1->id,
        'is_allowed' => true,
        'granted_by' => $superAdmin->id,
    ]);
});

test('super admin actions logged with actor_id', function () {
    $superAdmin = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);

    $this->actingAs($superAdmin)->post(route('platform.tenants.user-access.update', $this->tenant), [
        'user_id' => $this->user->id,
        'module_ids' => [$this->module1->id],
        'is_allowed' => true,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.module_access_granted',
        'subject_id' => $this->module1->id,
        'actor_user_id' => $superAdmin->id,
    ]);
});

test('grant multiple modules at once', function () {
    $response = $this->actingAs($this->admin)->post(route('tenant.users.access.update', $this->user), [
        'module_ids' => [$this->module1->id, $this->module2->id],
        'is_allowed' => true,
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('user_module_access', [
        'user_id' => $this->user->id,
        'training_module_id' => $this->module1->id,
        'is_allowed' => true,
    ]);
    $this->assertDatabaseHas('user_module_access', [
        'user_id' => $this->user->id,
        'training_module_id' => $this->module2->id,
        'is_allowed' => true,
    ]);
});

test('revoke multiple modules at once', function () {
    UserModuleAccess::create([
        'user_id' => $this->user->id,
        'training_module_id' => $this->module1->id,
        'tenant_id' => $this->tenant->id,
        'is_allowed' => true,
    ]);
    UserModuleAccess::create([
        'user_id' => $this->user->id,
        'training_module_id' => $this->module2->id,
        'tenant_id' => $this->tenant->id,
        'is_allowed' => true,
    ]);

    $response = $this->actingAs($this->admin)->post(route('tenant.users.access.update', $this->user), [
        'module_ids' => [$this->module1->id, $this->module2->id],
        'is_allowed' => false,
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('user_module_access', [
        'user_id' => $this->user->id,
        'training_module_id' => $this->module1->id,
        'is_allowed' => false,
    ]);
    $this->assertDatabaseHas('user_module_access', [
        'user_id' => $this->user->id,
        'training_module_id' => $this->module2->id,
        'is_allowed' => false,
    ]);
});

test('endpoint returns current access state', function () {
    UserModuleAccess::create([
        'user_id' => $this->user->id,
        'training_module_id' => $this->module1->id,
        'tenant_id' => $this->tenant->id,
        'is_allowed' => false,
    ]);

    $response = $this->actingAs($this->admin)->get(route('tenant.users.access.show', $this->user));

    $response->assertOk();
    $response->assertJson([
        'user_id' => $this->user->id,
        'modules' => [
            ['module_id' => $this->module1->id, 'is_allowed' => false],
            ['module_id' => $this->module2->id, 'is_allowed' => true],
        ],
    ]);
});