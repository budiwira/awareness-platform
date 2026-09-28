<?php

use App\Enums\TtxSessionInjectStatus;
use App\Enums\TtxSessionRole;
use App\Enums\TtxSessionStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxSession;
use App\Models\TtxSessionInject;
use App\Models\TtxSessionParticipant;
use App\Models\User;

function consoleRouteFixture(): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $facilitator = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $participant = User::factory()->create(['tenant_id' => $tenant->id]);

    $exercise = TtxExercise::create([
        'tenant_id' => $tenant->id,
        'title' => 'Console test exercise',
        'scenario' => 'Console scenario',
    ]);

    $inject = TtxInject::create([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'order' => 1,
        'title' => 'Console inject',
        'description' => 'Console inject description',
    ]);

    $session = TtxSession::forceCreate([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'title' => 'Console test session',
        'created_by' => $facilitator->id,
        'status' => TtxSessionStatus::InProgress,
        'started_at' => now(),
        'exercise_snapshot' => ['title' => $exercise->title, 'scenario' => $exercise->scenario],
    ]);

    TtxSessionParticipant::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'user_id' => $facilitator->id,
        'session_role' => TtxSessionRole::Facilitator,
    ]);

    TtxSessionParticipant::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'user_id' => $participant->id,
        'session_role' => TtxSessionRole::Security,
    ]);

    $sessionInject = TtxSessionInject::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'inject_id' => $inject->id,
        'order' => 1,
        'status' => TtxSessionInjectStatus::Active,
        'inject_snapshot' => ['title' => $inject->title, 'description' => $inject->description],
        'released_at' => now(),
        'released_by' => $admin->id,
    ]);

    return [$tenant, $admin, $facilitator, $participant, $exercise, $inject, $session, $sessionInject];
}

// ─── POSITIVE ──────────────────────────────────────────────

test('assigned active facilitator can access console route', function () {
    [, , $facilitator, , , , $session] = consoleRouteFixture();
    $this->actingAs($facilitator)
        ->get(route('tenant.ttx.sessions.console', $session))
        ->assertOk();
});

test('facilitator console renders official response read only', function () {
    $source = file_get_contents(resource_path('js/Pages/Tenant/Ttx/Sessions/FacilitatorConsole.vue'));

    expect($source)
        ->toContain('Respons Tim')
        ->toMatch('/tim sudah\s+merespons/')
        ->toContain('Belum merespons')
        ->toMatch('/Jika dilanjutkan, injeksi ini akan\s+dikunci\./')
        ->toContain('Koordinasi / Handoff', 'Responsibility Ownership', 'Objectives')
        ->not->toContain('<ResponseEditor')
        ->not->toContain('Simpan Respons')
        ->not->toContain('tenant.ttx.sessions.responses.store')
        ->not->toContain('tenant.ttx.sessions.responses.update');
});

// ─── NEGATIVE: Role-based ─────────────────────────────────

test('assigned non-facilitator participant gets 403', function () {
    [, , , $participant, , , $session] = consoleRouteFixture();
    $this->actingAs($participant)
        ->get(route('tenant.ttx.sessions.console', $session))
        ->assertForbidden();
});

// ─── NEGATIVE: Unassigned users ───────────────────────────

test('unassigned same-tenant user gets 403', function () {
    [$tenant, , , , , , $session] = consoleRouteFixture();
    $unassigned = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($unassigned)
        ->get(route('tenant.ttx.sessions.console', $session))
        ->assertForbidden();
});

test('unassigned Tenant Admin gets 403', function () {
    [$tenant, , , , , , $session] = consoleRouteFixture();
    $otherAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($otherAdmin)
        ->get(route('tenant.ttx.sessions.console', $session))
        ->assertForbidden();
});

test('unassigned Super Admin gets 403', function () {
    [, , , , , , $session] = consoleRouteFixture();
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'tenant_id' => null]);
    $this->actingAs($superAdmin)
        ->get(route('tenant.ttx.sessions.console', $session))
        ->assertForbidden();
});

// ─── NEGATIVE: Inactive ───────────────────────────────────

test('inactive assigned facilitator gets 403', function () {
    [, , $facilitator, , , , $session] = consoleRouteFixture();
    $facilitator->update(['is_active' => false]);
    $this->actingAs($facilitator->fresh())
        ->get(route('tenant.ttx.sessions.console', $session))
        ->assertForbidden();
});

// ─── NEGATIVE: Cross-tenant ───────────────────────────────

test('cross-tenant user gets 403', function () {
    [, , , , , , $session] = consoleRouteFixture();
    $otherTenant = Tenant::factory()->create();
    $crossUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
    $this->actingAs($crossUser)
        ->get(route('tenant.ttx.sessions.console', $session))
        ->assertForbidden();
});

// ─── NEGATIVE: Unauthenticated ────────────────────────────

test('unauthenticated user gets redirect to login', function () {
    [, , , , , , $session] = consoleRouteFixture();
    $this->get(route('tenant.ttx.sessions.console', $session))
        ->assertRedirect();
});
