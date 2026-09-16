<?php

use App\Models\AuditLog;
use App\Models\ModuleAssignment;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

function createAssignmentSubscription(Tenant $tenant, bool $includesAll = true, array $moduleIds = []): Package
{
    $package = Package::create([
        'name' => 'Assignment '.uniqid(),
        'slug' => 'assignment-'.uniqid(),
        'price_monthly' => 100,
        'max_users' => 100,
        'features' => ['training'],
        'includes_all_modules' => $includesAll,
        'is_active' => true,
    ]);

    if (! $includesAll) {
        $package->modules()->attach($moduleIds);
    }

    Subscription::create([
        'tenant_id' => $tenant->id,
        'package_id' => $package->id,
        'status' => 'active',
        'started_at' => now(),
    ]);

    return $package;
}

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
    $Package = Package::create(['name' => 'Ass-'.uniqid(), 'slug' => 'ass-'.uniqid(), 'price_monthly' => 100, 'max_users' => 100, 'features' => ['training'], 'includes_all_modules' => true, 'is_active' => true]);
    Subscription::create(['tenant_id' => $tenant->id, 'package_id' => $Package->id, 'status' => 'active', 'started_at' => now()]);
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

test('assignment form only lists active learners from the current tenant', function () {
    $tenant = Tenant::factory()->create();
    createAssignmentSubscription($tenant);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $learner = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    $inactiveLearner = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);
    $otherAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)->get(route('tenant.assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('users', 1)
            ->where('users.0.id', $learner->id)
            ->where('users', fn ($users) => collect($users)->pluck('id')->doesntContain($inactiveLearner->id)
                && collect($users)->pluck('id')->doesntContain($otherAdmin->id))
        );
});

test('privileged and inactive users cannot be assignment targets', function (string $targetType) {
    $tenant = Tenant::factory()->create();
    createAssignmentSubscription($tenant);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $target = match ($targetType) {
        'tenant admin' => User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]),
        default => User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]),
    };
    $module = TrainingModule::create([
        'title' => 'Eligible Module', 'content' => '...', 'duration_minutes' => 10,
        'is_active' => true, 'status' => 'published',
    ]);

    $this->actingAs($admin)->post(route('tenant.assignments.store'), [
        'user_id' => $target->id,
        'training_module_id' => $module->id,
    ])->assertSessionHasErrors('user_id');

    $this->assertDatabaseMissing('module_assignments', ['user_id' => $target->id]);
})->with(['tenant admin', 'inactive learner']);

test('super admin cannot be an assignment target', function () {
    $tenant = Tenant::factory()->create();
    createAssignmentSubscription($tenant);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $superAdmin = User::factory()->superAdmin()->create();
    $module = TrainingModule::create([
        'title' => 'Eligible Module', 'content' => '...', 'duration_minutes' => 10,
        'is_active' => true, 'status' => 'published',
    ]);

    $this->actingAs($admin)->post(route('tenant.assignments.store'), [
        'user_id' => $superAdmin->id,
        'training_module_id' => $module->id,
    ])->assertForbidden();

    $this->assertDatabaseMissing('module_assignments', ['user_id' => $superAdmin->id]);
});

test('already assigned module is excluded from learner assignable module ids', function () {
    $tenant = Tenant::factory()->create();
    createAssignmentSubscription($tenant);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $assignedModule = TrainingModule::create([
        'title' => 'Assigned', 'content' => '...', 'duration_minutes' => 10,
        'is_active' => true, 'status' => 'published',
    ]);
    $availableModule = TrainingModule::create([
        'title' => 'Available', 'content' => '...', 'duration_minutes' => 10,
        'is_active' => true, 'status' => 'published',
    ]);
    ModuleAssignment::create([
        'tenant_id' => $tenant->id,
        'user_id' => $learner->id,
        'training_module_id' => $assignedModule->id,
        'status' => 'assigned',
    ]);

    $this->actingAs($admin)->get(route('tenant.assignments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.0.assignable_module_ids', [$availableModule->id])
        );
});

test('duplicate assignment is rejected by the backend', function () {
    $tenant = Tenant::factory()->create();
    createAssignmentSubscription($tenant);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create([
        'title' => 'Duplicate Guard', 'content' => '...', 'duration_minutes' => 10,
        'is_active' => true, 'status' => 'published',
    ]);
    ModuleAssignment::create([
        'tenant_id' => $tenant->id,
        'user_id' => $learner->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
    ]);

    $this->actingAs($admin)->post(route('tenant.assignments.store'), [
        'user_id' => $learner->id,
        'training_module_id' => $module->id,
    ])->assertSessionHasErrors('training_module_id');

    expect(ModuleAssignment::where('user_id', $learner->id)
        ->where('training_module_id', $module->id)->count())->toBe(1);
});

test('unentitled module is rejected', function () {
    $tenant = Tenant::factory()->create();
    $entitledModule = TrainingModule::create([
        'title' => 'Entitled', 'content' => '...', 'duration_minutes' => 10,
        'is_active' => true, 'status' => 'published',
    ]);
    $unentitledModule = TrainingModule::create([
        'title' => 'Not Entitled', 'content' => '...', 'duration_minutes' => 10,
        'is_active' => true, 'status' => 'published',
    ]);
    createAssignmentSubscription($tenant, false, [$entitledModule->id]);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)->post(route('tenant.assignments.store'), [
        'user_id' => $learner->id,
        'training_module_id' => $unentitledModule->id,
    ])->assertSessionHasErrors('training_module_id');
});

test('inactive and unpublished modules are rejected', function (bool $isActive, string $status) {
    $tenant = Tenant::factory()->create();
    $module = TrainingModule::create([
        'title' => 'Unavailable', 'content' => '...', 'duration_minutes' => 10,
        'is_active' => $isActive, 'status' => $status,
    ]);
    createAssignmentSubscription($tenant, false, [$module->id]);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)->post(route('tenant.assignments.store'), [
        'user_id' => $learner->id,
        'training_module_id' => $module->id,
    ])->assertSessionHasErrors('training_module_id');

    $this->assertDatabaseMissing('module_assignments', [
        'user_id' => $learner->id,
        'training_module_id' => $module->id,
    ]);
})->with([
    'inactive' => [false, 'published'],
    'unpublished' => [true, 'draft'],
]);

test('historical assignment remains visible after learner and module become unavailable', function () {
    $tenant = Tenant::factory()->create();
    createAssignmentSubscription($tenant);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create([
        'title' => 'Historical', 'content' => '...', 'duration_minutes' => 10,
        'is_active' => true, 'status' => 'published',
    ]);
    $assignment = ModuleAssignment::create([
        'tenant_id' => $tenant->id,
        'user_id' => $learner->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
    ]);
    $learner->update(['is_active' => false]);
    $module->update(['is_active' => false, 'status' => 'archived']);

    $this->actingAs($admin)->get(route('tenant.assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assignments', 1)
            ->where('assignments.0.id', $assignment->id)
            ->where('assignments.0.user.name', $learner->name)
            ->where('assignments.0.module.title', 'Historical')
        );
});

test('tenant admin manual completion does not overwrite assessment score', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create([
        'title' => 'Scored Module', 'content' => '...', 'duration_minutes' => 10,
        'is_active' => true, 'status' => 'published',
    ]);
    $assignment = ModuleAssignment::create([
        'tenant_id' => $tenant->id,
        'user_id' => $learner->id,
        'training_module_id' => $module->id,
        'status' => 'in_progress',
        'score' => 37,
    ]);

    $this->actingAs($admin)->patch(route('tenant.assignments.update', $assignment), [
        'status' => 'completed',
        'score' => 100,
    ])->assertRedirect(route('tenant.assignments.index'));

    $assignment->refresh();
    expect($assignment->status)->toBe('completed')
        ->and($assignment->score)->toBe(37)
        ->and($assignment->completed_at)->not->toBeNull();
});

test('assignment history loads soft deleted learners without exposing them as eligible', function () {
    $tenant = Tenant::factory()->create();
    createAssignmentSubscription($tenant);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create([
        'title' => 'Historical Module', 'content' => '...', 'duration_minutes' => 10,
        'is_active' => true, 'status' => 'published',
    ]);
    ModuleAssignment::create([
        'tenant_id' => $tenant->id,
        'user_id' => $learner->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
    ]);
    $learner->delete();

    $this->actingAs($admin)->get(route('tenant.assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('users', 0)
            ->where('assignments.0.user.name', $learner->name)
            ->where('assignments.0.module.title', $module->title)
        );
});

test('assignment form reports no eligible learners when all learners are inactive', function () {
    $tenant = Tenant::factory()->create();
    createAssignmentSubscription($tenant);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);
    User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)->get(route('tenant.assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('users', 0));
});

test('notification failure does not turn a successful assignment into a failed request', function () {
    $tenant = Tenant::factory()->create();
    createAssignmentSubscription($tenant);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create([
        'title' => 'Notification Failure', 'content' => '...', 'duration_minutes' => 10,
        'is_active' => true, 'status' => 'published',
    ]);
    Event::listen(NotificationSending::class, function () {
        throw new RuntimeException('Notification transport unavailable');
    });
    Log::spy();

    $this->actingAs($admin)->post(route('tenant.assignments.store'), [
        'user_id' => $learner->id,
        'training_module_id' => $module->id,
    ])->assertRedirect(route('tenant.assignments.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('module_assignments', [
        'user_id' => $learner->id,
        'training_module_id' => $module->id,
        'tenant_id' => $tenant->id,
    ]);
    expect(AuditLog::where('action', 'module.assigned')->exists())->toBeTrue();
    Log::shouldHaveReceived('warning')->once();
});
