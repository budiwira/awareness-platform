<?php

use App\Models\ModuleAssignment;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;

test('user can view their own training assignments', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create(['title' => 'Test', 'content' => '...', 'duration_minutes' => 10]);

    ModuleAssignment::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
    ]);

    $this->actingAs($user)
        ->get(route('user.training.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('User/MyTraining/Index'));
});

test('user can mark assignment as completed', function () {
    $tenant = Tenant::factory()->create();
    $package = Package::create([
        'name' => 'Training', 'slug' => 'training-'.uniqid(), 'price_monthly' => 100,
        'max_users' => 100, 'features' => ['training'], 'includes_all_modules' => true, 'is_active' => true,
    ]);
    Subscription::create([
        'tenant_id' => $tenant->id, 'package_id' => $package->id, 'status' => 'active', 'started_at' => now(),
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create(['title' => 'Test', 'content' => '...', 'duration_minutes' => 10]);

    $assignment = ModuleAssignment::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
    ]);

    $this->actingAs($user)
        ->patch(route('user.training.complete', $assignment->id))
        ->assertRedirect(route('user.training.index'));

    $this->assertDatabaseHas('module_assignments', [
        'id' => $assignment->id,
        'status' => 'completed',
    ]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'training.completed']);
});

test('user cannot view another user assignment', function () {
    $tenant = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenant->id]);
    $userB = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create(['title' => 'Test', 'content' => '...', 'duration_minutes' => 10]);

    $assignment = ModuleAssignment::create([
        'user_id' => $userB->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
    ]);

    $this->actingAs($userA)
        ->get(route('user.training.show', $assignment->id))
        ->assertForbidden();
});
