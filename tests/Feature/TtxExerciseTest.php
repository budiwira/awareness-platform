<?php

use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxPlaybook;
use App\Models\TtxRunbook;
use App\Models\TtxTeam;
use App\Models\User;

function makeTtxFixture(): array
{
    $tenant = Tenant::factory()->create();
    $Package = Package::create([
        'name' => 'Ttx-'.uniqid(),
        'slug' => 'ttx-'.uniqid(),
        'price_monthly' => 100,
        'max_users' => 100,
        'features' => ['ttx'],
        'includes_all_modules' => false,
        'is_active' => true,
    ]);
    Subscription::create(['tenant_id' => $tenant->id, 'package_id' => $Package->id, 'status' => 'active', 'started_at' => now()]);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    return [$tenant, $admin, $user];
}

test('tenant admin can create exercise', function () {
    [$tenant, $admin] = makeTtxFixture();

    $this->actingAs($admin)
        ->post(route('tenant.ttx.exercises.store'), ['title' => 'TTX Ransomware'])
        ->assertRedirect();

    $this->assertDatabaseHas('ttx_exercises', ['title' => 'TTX Ransomware', 'phase' => 'planning']);
});

test('tenant admin can create team', function () {
    [$tenant, $admin] = makeTtxFixture();
    $ex = TtxExercise::create(['tenant_id' => $tenant->id, 'title' => 'TTX']);

    $this->actingAs($admin)
        ->post(route('tenant.ttx.teams.store', $ex), ['name' => 'Tim Recovery'])
        ->assertRedirect();

    $this->assertDatabaseHas('ttx_teams', ['name' => 'Tim Recovery', 'exercise_id' => $ex->id]);
});

test('cannot add member from another tenant', function () {
    [$tenant, $admin] = makeTtxFixture();
    $otherTenant = Tenant::factory()->create();
    $outsider = User::factory()->create(['tenant_id' => $otherTenant->id]);

    $ex = TtxExercise::create(['tenant_id' => $tenant->id, 'title' => 'TTX']);
    $team = TtxTeam::create(['tenant_id' => $tenant->id, 'exercise_id' => $ex->id, 'name' => 'Tim A']);

    $this->actingAs($admin)
        ->post(route('tenant.ttx.teams.members.store', $team), ['user_id' => $outsider->id, 'role_in_team' => 'member'])
        ->assertStatus(403);
});

test('regular user cannot access exercise management', function () {
    [$tenant, $admin, $user] = makeTtxFixture();
    $ex = TtxExercise::create(['tenant_id' => $tenant->id, 'title' => 'TTX']);

    $this->actingAs($user)->get(route('tenant.ttx.exercises.show', $ex))->assertForbidden();
});

test('exercise references must belong to the admin tenant', function (string $field, string $model, bool $ownTenant) {
    [$tenant, $admin] = makeTtxFixture();
    $reference = $model::create([
        'tenant_id' => $ownTenant ? $tenant->id : Tenant::factory()->create()->id,
        'title' => 'Reference',
    ]);

    $response = $this->actingAs($admin)->post(route('tenant.ttx.exercises.store'), [
        'title' => 'Referenced exercise',
        $field => $reference->id,
    ]);

    if ($ownTenant) {
        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ttx_exercises', [
            'tenant_id' => $tenant->id,
            $field => $reference->id,
        ]);
    } else {
        $response->assertSessionHasErrors($field);
        $this->assertDatabaseCount('ttx_exercises', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'ttx.exercise_created']);
    }
})->with([
    'own playbook' => ['playbook_id', TtxPlaybook::class, true],
    'foreign playbook' => ['playbook_id', TtxPlaybook::class, false],
    'own runbook' => ['runbook_id', TtxRunbook::class, true],
    'foreign runbook' => ['runbook_id', TtxRunbook::class, false],
]);

test('exercise mutations reject foreign tenants and learners without writes', function (string $endpoint, array $payload, bool $foreignTenant) {
    [$tenant, $admin, $learner] = makeTtxFixture();
    $exercise = TtxExercise::create([
        'tenant_id' => $foreignTenant ? Tenant::factory()->create()->id : $tenant->id,
        'title' => 'Protected exercise',
        'phase' => 'evaluation',
    ]);
    $original = $exercise->fresh()->getAttributes();

    $this->actingAs($foreignTenant ? $admin : $learner)
        ->post(route($endpoint, $exercise), $payload)
        ->assertForbidden();

    expect($exercise->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseCount('ttx_teams', 0);
    $this->assertDatabaseCount('ttx_injects', 0);
    $this->assertDatabaseCount('ttx_scores', 0);
    $this->assertDatabaseMissing('audit_logs', ['subject_id' => $exercise->id, 'subject_type' => TtxExercise::class]);
})->with([
    'team' => ['tenant.ttx.teams.store', ['name' => 'Unauthorized team']],
    'inject' => ['tenant.ttx.exercises.injects.store', ['title' => 'Unauthorized inject']],
    'advance' => ['tenant.ttx.exercises.advance', []],
    'evaluate' => ['tenant.ttx.exercises.evaluate.store', ['aar_notes' => 'Unauthorized evaluation']],
])->with(['foreign tenant admin' => true, 'learner' => false]);
