<?php

use App\Models\ModuleAssignment;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;

test('tenant admin can view assignments for their users', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create(['title' => 'Test', 'content' => '...', 'duration_minutes' => 10]);

    ModuleAssignment::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
    ]);

    $this->actingAs($admin)
        ->get(route('tenant.assignments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Tenant/Assignments/Index'));
});

test('tenant admin can assign a module to a user', function () {
    $tenant = Tenant::factory()->create();
    $plan = \App\Models\Plan::create(['name' => 'Ass-' . uniqid(), 'slug' => 'ass-' . uniqid(), 'price_monthly' => 100, 'max_users' => 100, 'features' => ['training'], 'includes_all_modules' => true, 'is_active' => true]);
    \App\Models\Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => 'active', 'started_at' => now()]);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create(['title' => 'Test', 'content' => '...', 'duration_minutes' => 10, 'is_active' => true, 'status' => 'published']);

    $this->actingAs($admin)
        ->post(route('tenant.assignments.store'), [
            'user_id' => $user->id,
            'training_module_id' => $module->id,
        ])
        ->assertRedirect(route('tenant.assignments.index'));

    $this->assertDatabaseHas('module_assignments', [
        'user_id' => $user->id,
        'training_module_id' => $module->id,
        'tenant_id' => $tenant->id,
    ]);
});

test('tenant admin cannot assign module to user from another tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    
    $adminA = User::factory()->tenantAdmin()->create(['tenant_id' => $tenantA->id]);
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
    $module = TrainingModule::create(['title' => 'Test', 'content' => '...', 'duration_minutes' => 10, 'is_active' => true]);

    $this->actingAs($adminA)
        ->post(route('tenant.assignments.store'), [
            'user_id' => $userB->id,
            'training_module_id' => $module->id,
        ])
        ->assertStatus(403); // Atau redirect dengan error, tergantung implementasi abort()
});