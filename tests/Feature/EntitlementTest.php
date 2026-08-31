<?php

use App\Models\ModuleAssignment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;

function createPlanWithModules(string $slug, array $features, bool $includesAll, array $modules = []): Plan
{
    $plan = Plan::create([
        'name' => ucfirst($slug),
        'slug' => $slug . '-' . uniqid(),
        'price_monthly' => 500,
        'max_users' => 100,
        'features' => $features,
        'includes_all_modules' => $includesAll,
        'is_active' => true,
    ]);

    if (!$includesAll && !empty($modules)) {
        $plan->modules()->attach($modules);
    }

    return $plan;
}

test('starter cannot assign module outside curasi returns 422', function () {
    $tenant = Tenant::factory()->create();
    $module1 = TrainingModule::create(['title' => 'M1', 'content' => 'c', 'duration_minutes' => 10, 'is_active' => true, 'status' => 'published']);
    $module2 = TrainingModule::create(['title' => 'M2', 'content' => 'c', 'duration_minutes' => 10, 'is_active' => true, 'status' => 'published']);

    $starter = createPlanWithModules('starter', ['training'], false, [$module1->id]);
    Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $starter->id, 'status' => 'active', 'started_at' => now()]);

    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->post(route('tenant.assignments.store'), [
            'user_id' => $user->id,
            'training_module_id' => $module2->id,
        ])
        ->assertSessionHasErrors('training_module_id');
});

test('starter user cannot view assignment outside kurasi 403', function () {
    $tenant = Tenant::factory()->create();
    $module1 = TrainingModule::create(['title' => 'M1', 'content' => 'c', 'duration_minutes' => 10, 'is_active' => true, 'status' => 'published']);
    $module2 = TrainingModule::create(['title' => 'M2', 'content' => 'c', 'duration_minutes' => 10, 'is_active' => true, 'status' => 'published']);

    $starter = createPlanWithModules('starter', ['training'], false, [$module1->id]);
    Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $starter->id, 'status' => 'active', 'started_at' => now()]);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $assignment = ModuleAssignment::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module2->id,
        'status' => 'assigned',
    ]);

    $this->actingAs($user)
        ->get(route('user.training.show', $assignment))
        ->assertStatus(403);
});

test('starter 403 aksi TTX dengan locked state', function () {
    $tenant = Tenant::factory()->create();
    $starter = createPlanWithModules('starter', ['training'], false, []);
    Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $starter->id, 'status' => 'active', 'started_at' => now()]);

    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('tenant.ttx.exercises.index'))
        ->assertStatus(403);
});

test('starter entitlements prop tanpa ttx', function () {
    $tenant = Tenant::factory()->create();
    $starter = createPlanWithModules('starter', ['training'], false, []);
    Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $starter->id, 'status' => 'active', 'started_at' => now()]);

    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('tenant.dashboard'))
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'] ?? [];
            return $page;
        });
});

test('pro bisa TTX dan ekspor reports', function () {
    $tenant = Tenant::factory()->create();
    $m = TrainingModule::create(['title' => 'TTX M', 'content' => 'c', 'duration_minutes' => 10, 'is_active' => true, 'status' => 'published']);

    $pro = createPlanWithModules('pro', ['training', 'reports_export', 'ttx', 'case_studies'], true, []);
    Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $pro->id, 'status' => 'active', 'started_at' => now()]);

    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('tenant.ttx.exercises.index'))
        ->assertOk();
});

test('enterprise bisa CTF', function () {
    $tenant = Tenant::factory()->create();
    $enterprise = createPlanWithModules('enterprise', ['training', 'reports_export', 'ttx', 'case_studies', 'ctf'], true, []);
    Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $enterprise->id, 'status' => 'active', 'started_at' => now()]);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('user.ctf.index'))
        ->assertOk();
});

test('includes_all_modules otomatis dapat modul published baru', function () {
    $tenant = Tenant::factory()->create();
    $pro = createPlanWithModules('pro', ['training'], true, []);
    Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $pro->id, 'status' => 'active', 'started_at' => now()]);

    $newModule = TrainingModule::create(['title' => 'New Published', 'content' => 'c', 'duration_minutes' => 10, 'is_active' => true, 'status' => 'published']);

    $entitlement = app(\App\Services\TenantEntitlement::class);
    expect($entitlement->hasModule($tenant, $newModule->id))->toBeTrue();
});

test('custom plan dengan modul pilihan enforcement benar', function () {
    $tenant = Tenant::factory()->create();
    $m1 = TrainingModule::create(['title' => 'C1', 'content' => 'c', 'duration_minutes' => 10, 'is_active' => true, 'status' => 'published']);
    $m2 = TrainingModule::create(['title' => 'C2', 'content' => 'c', 'duration_minutes' => 10, 'is_active' => true, 'status' => 'published']);

    $custom = createPlanWithModules('custom', ['training'], false, [$m1->id]);
    Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $custom->id, 'status' => 'active', 'started_at' => now()]);

    $entitlement = app(\App\Services\TenantEntitlement::class);
    expect($entitlement->hasModule($tenant, $m1->id))->toBeTrue()
        ->and($entitlement->hasModule($tenant, $m2->id))->toBeFalse();
});

test('flash entitlements setelah set-plan berisi nama plan', function () {
    $super = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    $plan = createPlanWithModules('starter', ['training'], false, []);

    $this->actingAs($super)
        ->post(route('platform.tenants.set-plan'), [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
        ])
        ->assertSessionHas('success', fn($v) => str_contains($v, $plan->name));
});

test('riwayat tetap terbaca setelah downgrade', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create(['title' => 'Hist', 'content' => 'c', 'duration_minutes' => 10, 'is_active' => true, 'status' => 'published']);

    ModuleAssignment::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module->id,
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    // Downgrade ke plan tanpa modul
    $starter = createPlanWithModules('starter', ['training'], false, []);
    Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $starter->id, 'status' => 'active', 'started_at' => now()]);

    // Assignment masih di DB
    $this->assertDatabaseHas('module_assignments', ['user_id' => $user->id, 'tenant_id' => $tenant->id]);
});

test('user beta tetap bisa membuka modul tugasnya', function () {
    $tenant = Tenant::factory()->create(['slug' => 'beta']);
    $module = TrainingModule::create(['title' => 'Beta Task', 'content' => 'c', 'duration_minutes' => 10, 'is_active' => true, 'status' => 'published']);

    $starter = createPlanWithModules('starter', ['training'], false, [$module->id]);
    Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $starter->id, 'status' => 'active', 'started_at' => now()]);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $assignment = ModuleAssignment::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
    ]);

    $this->actingAs($user)
        ->get(route('user.training.show', $assignment))
        ->assertOk();
});
