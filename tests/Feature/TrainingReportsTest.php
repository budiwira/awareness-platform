<?php

use App\Models\ModuleAssignment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('user sees own score page with stats', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create(['title' => 'M', 'content' => 'x', 'duration_minutes' => 10]);
    $quiz = Quiz::create(['training_module_id' => $module->id, 'title' => 'Q', 'passing_score' => 70]);

    QuizAttempt::create([
        'quiz_id' => $quiz->id,
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'score' => 80,
        'passed' => true,
        'answers' => [],
    ]);

    $this->actingAs($user)
        ->get(route('user.score'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/MyScore/Index')
            ->where('stats.avg_score', 80)
            ->has('attempts', 1)
        );
});
test('tenant admin can export csv report', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Budi Export']);

    $response = $this->actingAs($admin)->get(route('tenant.reports.export'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('Budi Export');
});

test('regular user cannot export report', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->get(route('tenant.reports.export'))->assertForbidden();
});

test('tenant admin reports only include own tenant data', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $adminA = User::factory()->tenantAdmin()->create(['tenant_id' => $tenantA->id]);
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

    $module = TrainingModule::create(['title' => 'M', 'content' => 'x', 'duration_minutes' => 10]);

    ModuleAssignment::create(['user_id' => $userA->id, 'tenant_id' => $tenantA->id, 'training_module_id' => $module->id, 'status' => 'assigned']);
    ModuleAssignment::create(['user_id' => $userB->id, 'tenant_id' => $tenantB->id, 'training_module_id' => $module->id, 'status' => 'assigned']);

    $this->actingAs($adminA)
        ->get(route('tenant.reports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Reports/Index')
            ->where('stats.assignments', 1)
        );
});