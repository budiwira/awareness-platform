<?php

use App\Models\ModuleAssignment;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;

test('platform reports renders report page with rows and platform average', function () {
    $super = User::factory()->superAdmin()->create();
    $acme = Tenant::factory()->create(['name' => 'Acme Corp']);
    $beta = Tenant::factory()->create(['name' => 'Beta Corp']);
    $acmeUsers = User::factory()->count(2)->create(['tenant_id' => $acme->id]);
    $betaUser = User::factory()->create(['tenant_id' => $beta->id]);
    $module = TrainingModule::create(['title' => 'Report Module', 'content' => 'x', 'duration_minutes' => 10]);
    ModuleAssignment::create([
        'tenant_id' => $acme->id, 'user_id' => $acmeUsers[0]->id,
        'training_module_id' => $module->id, 'status' => 'completed', 'score' => 80,
    ]);
    ModuleAssignment::create([
        'tenant_id' => $acme->id, 'user_id' => $acmeUsers[1]->id,
        'training_module_id' => $module->id, 'status' => 'assigned',
    ]);
    ModuleAssignment::create([
        'tenant_id' => $beta->id, 'user_id' => $betaUser->id,
        'training_module_id' => $module->id, 'status' => 'completed', 'score' => 40,
    ]);

    $this->actingAs($super)
        ->get(route('platform.reports'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Reports/Index')
            ->has('rows', 2)
            ->where('rows.0.name', 'Acme Corp')
            ->where('rows.0.users', 2)
            ->where('rows.0.assignments', 2)
            ->where('rows.0.completion_rate', 50)
            ->where('rows.0.avg_awareness', 80)
            ->where('platform_avg', 60)
        );
});

test('platform dashboard renders operational dashboard', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)->get(route('platform.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Dashboard')
            ->has('summary')
            ->has('top_tenants_by_risk')
            ->has('plan_distribution')
            ->has('phishing_adoption')
        );
});

test('tenant admin cannot access platform report', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->get(route('platform.reports'))->assertForbidden();
    $this->actingAs($admin)->get(route('platform.dashboard'))->assertForbidden();
});

test('platform dashboard and reports route names remain stable', function () {
    expect(route('platform.dashboard', [], false))->toBe('/platform/dashboard')
        ->and(route('platform.reports', [], false))->toBe('/platform/reports');
});
