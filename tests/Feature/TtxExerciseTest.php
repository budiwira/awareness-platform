<?php

use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TtxExercise;
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
