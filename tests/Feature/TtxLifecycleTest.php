<?php

use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxTeam;
use App\Models\User;

test('full ttx lifecycle: create -> team -> advance -> evaluate -> completed', function () {
    $tenant = Tenant::factory()->create();
    $Package = Package::create(['name' => 'Lif-'.uniqid(), 'slug' => 'lif-'.uniqid(), 'price_monthly' => 100, 'max_users' => 100, 'features' => ['ttx'], 'includes_all_modules' => false, 'is_active' => true]);
    Subscription::create(['tenant_id' => $tenant->id, 'package_id' => $Package->id, 'status' => 'active', 'started_at' => now()]);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $member = User::factory()->create(['tenant_id' => $tenant->id]);

    // 1. Create exercise (planning)
    $this->actingAs($admin)
        ->post(route('tenant.ttx.exercises.store'), ['title' => 'TTX Lifecycle'])
        ->assertRedirect();
    $ex = TtxExercise::first();
    expect($ex->phase)->toBe('planning');

    // 2. Team + member
    $this->actingAs($admin)
        ->post(route('tenant.ttx.teams.store', $ex), ['name' => 'Tim Incident Response'])
        ->assertRedirect();
    $team = TtxTeam::first();

    $this->actingAs($admin)
        ->post(route('tenant.ttx.teams.members.store', $team), ['user_id' => $member->id, 'role_in_team' => 'lead'])
        ->assertRedirect();

    // 3. Advance: preparation -> execution -> evaluation
    foreach (['preparation', 'execution', 'evaluation'] as $phase) {
        $this->actingAs($admin)->post(route('tenant.ttx.exercises.advance', $ex))->assertRedirect();
        expect($ex->fresh()->phase)->toBe($phase);
    }

    // 4. Evaluate: score + AAR -> completed
    $this->actingAs($admin)
        ->post(route('tenant.ttx.exercises.evaluate.store', $ex), [
            'aar_notes' => 'Koordinasi baik; eskalasi perlu dipercepat.',
            'corrective_actions' => "Perbarui SOP eskalasi\nLatihan komunikasi rutin",
            'scores' => [$member->id => 90],
        ])
        ->assertRedirect();

    expect($ex->fresh()->phase)->toBe('completed');
    $this->assertDatabaseHas('ttx_scores', ['user_id' => $member->id, 'score' => 90]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.evaluated']);
});
