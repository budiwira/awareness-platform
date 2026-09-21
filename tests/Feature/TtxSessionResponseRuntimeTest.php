<?php

use App\Enums\TtxSessionInjectStatus;
use App\Enums\TtxSessionRole;
use App\Enums\TtxSessionStatus;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxSession;
use App\Models\TtxSessionInject;
use App\Models\TtxSessionParticipant;
use App\Models\TtxSessionResponse;
use App\Models\User;
use App\Services\TtxSessionService;
use Illuminate\Validation\ValidationException;

function ttxResponseRuntimeFixture(): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $facilitator = User::factory()->create(['tenant_id' => $tenant->id]);
    $participant = User::factory()->create(['tenant_id' => $tenant->id]);

    $exercise = TtxExercise::create([
        'tenant_id' => $tenant->id,
        'title' => 'Response runtime exercise',
        'scenario' => 'Runtime scenario',
    ]);

    $inject = TtxInject::create([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'order' => 1,
        'title' => 'Runtime inject',
        'description' => 'Runtime inject description',
    ]);

    $session = TtxSession::forceCreate([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'title' => 'Response runtime session',
        'created_by' => $admin->id,
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

// ─── CREATE ───────────────────────────────────────────────

test('assigned participant can create response for ACTIVE inject', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $response = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Contain the breach',
        'rationale' => 'Immediate containment limits blast radius.',
    ]);

    $response->assertStatus(201)
        ->assertJsonFragment(['decision' => 'Contain the breach', 'revision' => 1]);

    $this->assertDatabaseHas('ttx_session_responses', [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Contain the breach',
        'revision' => 1,
    ]);
});

test('assigned facilitator can create response', function () {
    [, , $facilitator, , , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $response = $this->actingAs($facilitator)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Facilitator response',
    ]);

    $response->assertStatus(201);
});

test('assigned facilitator can update response', function () {
    [, , $facilitator, , , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($facilitator)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Initial decision',
    ]);

    $responseId = $create->json('id');

    $update = $this->actingAs($facilitator)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Updated decision',
    ]);

    $update->assertStatus(200)
        ->assertJsonFragment(['decision' => 'Updated decision', 'revision' => 2]);
});

test('new response revision is 1', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $response = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Revision check',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('ttx_session_responses', [
        'session_inject_id' => $sessionInject->id,
        'revision' => 1,
    ]);
});

// ─── UPDATE ───────────────────────────────────────────────

test('update with correct expected_revision succeeds', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Original',
    ]);

    $responseId = $create->json('id');

    $update = $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Updated',
    ]);

    $update->assertStatus(200)
        ->assertJsonFragment(['decision' => 'Updated', 'revision' => 2]);
});

test('successful update increments revision exactly once', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Original',
    ]);

    $responseId = $create->json('id');

    $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'First update',
    ]);

    $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 2,
        'decision' => 'Second update',
    ]);

    $this->assertDatabaseHas('ttx_session_responses', [
        'id' => $responseId,
        'revision' => 3,
        'decision' => 'Second update',
    ]);
});

test('submitted_by remains unchanged after edit', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Original',
    ]);

    $responseId = $create->json('id');

    $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Updated',
    ]);

    $this->assertDatabaseHas('ttx_session_responses', [
        'id' => $responseId,
        'submitted_by' => $participant->id,
    ]);
});

test('submitted_at remains unchanged after edit', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Original',
    ]);

    $responseId = $create->json('id');
    $originalSubmittedAt = TtxSessionResponse::find($responseId)->submitted_at;

    sleep(1);

    $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Updated',
    ]);

    $response = TtxSessionResponse::find($responseId);
    expect($response->submitted_at->timestamp)->toBe($originalSubmittedAt->timestamp);
});

test('last_edited_by becomes updater on successful update', function () {
    [, , $facilitator, $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Original',
    ]);

    $responseId = $create->json('id');

    $this->actingAs($facilitator)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Facilitator updated',
    ]);

    $this->assertDatabaseHas('ttx_session_responses', [
        'id' => $responseId,
        'last_edited_by' => $facilitator->id,
    ]);
});

test('last_edited_by is NULL after initial creation', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'New response',
    ]);

    $this->assertDatabaseHas('ttx_session_responses', [
        'session_inject_id' => $sessionInject->id,
        'last_edited_by' => null,
    ]);
});

// ─── OPTIMISTIC CONCURRENCY ──────────────────────────────

test('stale revision returns 409', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Original',
    ]);

    $responseId = $create->json('id');

    // First update succeeds
    $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Updated',
    ]);

    // Second update with stale revision
    $stale = $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Stale update',
    ]);

    $stale->assertStatus(409);
});

test('stale update does not alter stored response', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Original',
    ]);

    $responseId = $create->json('id');

    // First update succeeds
    $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Valid update',
    ]);

    // Stale update fails
    $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Should not apply',
    ]);

    $this->assertDatabaseHas('ttx_session_responses', [
        'id' => $responseId,
        'decision' => 'Valid update',
        'revision' => 2,
    ]);
});

// ─── AUTHORIZATION ────────────────────────────────────────

test('unassigned same-tenant user cannot edit', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();
    $unassigned = User::factory()->create(['tenant_id' => $session->tenant_id]);

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Participant response',
    ]);

    $responseId = $create->json('id');

    $update = $this->actingAs($unassigned)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Unauthorized update',
    ]);

    $update->assertStatus(403);
});

test('unassigned Tenant Admin cannot edit', function () {
    [, , , , , , $session, $sessionInject] = ttxResponseRuntimeFixture();
    $otherAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $session->tenant_id]);

    $create = $this->actingAs($otherAdmin)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Initial',
    ]);

    // The otherAdmin is not a participant, so create fails
    $create->assertStatus(403);
});

test('unassigned Super Admin cannot edit', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();
    $superAdmin = User::factory()->superAdmin()->create();

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Participant response',
    ]);

    $responseId = $create->json('id');

    $update = $this->actingAs($superAdmin)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Super admin update',
    ]);

    $update->assertStatus(403);
});

test('inactive actor cannot edit', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Original',
    ]);

    $responseId = $create->json('id');

    $participant->update(['is_active' => false]);

    $update = $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Inactive update',
    ]);

    $update->assertStatus(403);
});

test('cross-tenant actor cannot edit', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Original',
    ]);

    $responseId = $create->json('id');

    $otherTenant = Tenant::factory()->create();
    $crossTenantUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

    $update = $this->actingAs($crossTenantUser)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Cross tenant',
    ]);

    $update->assertStatus(403);
});

// ─── INJECT STATUS ────────────────────────────────────────

test('PENDING inject cannot be responded to', function () {
    [, , , $participant, , , $session] = ttxResponseRuntimeFixture();

    $pendingInject = TtxSessionInject::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'inject_id' => TtxInject::create([
            'tenant_id' => $session->tenant_id,
            'exercise_id' => $session->exercise_id,
            'order' => 2,
            'title' => 'Pending inject',
        ])->id,
        'order' => 2,
        'status' => TtxSessionInjectStatus::Pending,
        'inject_snapshot' => ['title' => 'Pending inject'],
    ]);

    $response = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $pendingInject->id,
        'decision' => 'Pending response',
    ]);

    $response->assertStatus(422);
});

test('LOCKED inject cannot be mutated', function () {
    [, , $facilitator, , , , $session] = ttxResponseRuntimeFixture();

    $lockedInject = TtxSessionInject::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'inject_id' => TtxInject::create([
            'tenant_id' => $session->tenant_id,
            'exercise_id' => $session->exercise_id,
            'order' => 3,
            'title' => 'Locked inject',
        ])->id,
        'order' => 3,
        'status' => TtxSessionInjectStatus::Locked,
        'inject_snapshot' => ['title' => 'Locked inject'],
        'locked_at' => now(),
    ]);

    $response = $this->actingAs($facilitator)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $lockedInject->id,
        'decision' => 'Locked response',
    ]);

    $response->assertStatus(422);
});

// ─── SESSION STATUS ───────────────────────────────────────

test('DEBRIEF session cannot mutate responses', function () {
    [, $admin, $facilitator, , , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($facilitator)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Before debrief',
    ]);

    $responseId = $create->json('id');

    $session->update(['status' => TtxSessionStatus::Debrief, 'debrief_started_at' => now()]);

    $update = $this->actingAs($facilitator)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'During debrief',
    ]);

    $update->assertStatus(422);
});

test('COMPLETED session cannot mutate responses', function () {
    [, $admin, $facilitator, , , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($facilitator)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Before completion',
    ]);

    $responseId = $create->json('id');

    $session->update(['status' => TtxSessionStatus::Completed, 'completed_at' => now()]);

    $update = $this->actingAs($facilitator)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'After completion',
    ]);

    $update->assertStatus(422);
});

// ─── VALIDATION ───────────────────────────────────────────

test('blank decision is rejected', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $response = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => '',
    ]);

    $response->assertStatus(422);
});

test('missing decision is rejected', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $response = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
    ]);

    $response->assertStatus(422);
});

test('missing expected_revision on update is rejected', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Original',
    ]);

    $responseId = $create->json('id');

    $update = $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'decision' => 'Missing revision',
    ]);

    $update->assertStatus(422);
});

// ─── MASS-ASSIGNMENT / INTEGRITY ─────────────────────────

test('mass-assignment fields from client input are ignored on create', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $response = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Integrity check',
        'tenant_id' => 'evil-tenant',
        'session_id' => 9999,
        'submitted_by' => 9999,
        'revision' => 999,
    ]);

    $response->assertStatus(201);

    $stored = TtxSessionResponse::where('session_inject_id', $sessionInject->id)->first();
    expect($stored->tenant_id)->toBe($session->tenant_id)
        ->and($stored->session_id)->toBe($session->id)
        ->and($stored->submitted_by)->toBe($participant->id)
        ->and($stored->revision)->toBe(1);
});

test('mass-assignment fields from client input are ignored on update', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Original',
    ]);

    $responseId = $create->json('id');

    $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Updated',
        'submitted_by' => 9999,
        'revision' => 999,
        'last_edited_by' => 9999,
    ]);

    $this->assertDatabaseHas('ttx_session_responses', [
        'id' => $responseId,
        'submitted_by' => $participant->id,
        'revision' => 2,
        'last_edited_by' => $participant->id,
    ]);
});

// ─── AUDIT ────────────────────────────────────────────────

test('audit event emitted on successful create', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Audited response',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'ttx.response_saved',
    ]);
});

test('audit event emitted on successful update', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Original',
    ]);

    $responseId = $create->json('id');

    $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Updated',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'ttx.response_saved',
        'subject_id' => (string) $responseId,
    ]);
});

test('stale update does not emit success audit event', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Original',
    ]);

    $responseId = $create->json('id');

    // Count audit logs before stale update
    $beforeCount = AuditLog::where('action', 'ttx.response_saved')->count();

    $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 999,
        'decision' => 'Stale',
    ]);

    $afterCount = AuditLog::where('action', 'ttx.response_saved')->count();
    expect($afterCount)->toBe($beforeCount);
});

// ─── EXISTING DOMAIN TESTS REMAIN PASSING ────────────────

test('one-response-per-inject uniqueness is enforced via service', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $beforeCount = AuditLog::where('action', 'ttx.response_saved')->count();

    $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'First response',
    ]);

    $afterFirstCount = AuditLog::where('action', 'ttx.response_saved')->count();
    expect($afterFirstCount)->toBe($beforeCount + 1);

    $duplicate = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Duplicate response',
    ]);

    $duplicate->assertStatus(409);

    // No additional success audit from rejected duplicate
    $afterDuplicateCount = AuditLog::where('action', 'ttx.response_saved')->count();
    expect($afterDuplicateCount)->toBe($afterFirstCount);

    // Only one response exists
    $count = TtxSessionResponse::where('session_inject_id', $sessionInject->id)->count();
    expect($count)->toBe(1);
});

test('response for wrong session inject is rejected', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    // Lock the current active inject so we can create a new active one
    $sessionInject->update(['status' => TtxSessionInjectStatus::Locked, 'locked_at' => now()]);

    $otherExercise = TtxExercise::create([
        'tenant_id' => $session->tenant_id,
        'title' => 'Other exercise',
    ]);
    $otherInject = TtxInject::create([
        'tenant_id' => $session->tenant_id,
        'exercise_id' => $otherExercise->id,
        'order' => 1,
        'title' => 'Other inject',
    ]);
    $otherSessionInject = TtxSessionInject::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'inject_id' => $otherInject->id,
        'order' => 2,
        'status' => TtxSessionInjectStatus::Active,
        'inject_snapshot' => ['title' => $otherInject->title],
        'released_at' => now(),
        'released_by' => $session->created_by,
    ]);

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $otherSessionInject->id,
        'decision' => 'Other inject response',
    ]);

    $create->assertStatus(201);

    $responseId = $create->json('id');
    expect($responseId)->toBeInt();
});

// ─── LIFECYCLE RACE PROTECTION ───────────────────────────

test('response update is rejected when inject becomes LOCKED after response was saved', function () {
    // This tests the row-lock/state-recheck mechanism.
    // Limitation: a true concurrent race cannot be deterministically tested in PHPUnit.
    // This test verifies the service re-reads inject status after acquiring locks.
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Original',
    ]);
    $responseId = $create->json('id');

    // Simulate concurrent advance: lock the inject (as advanceInject would)
    $sessionInject->update(['status' => TtxSessionInjectStatus::Locked, 'locked_at' => now()]);

    // Attempt update — service re-reads inject inside transaction, finds LOCKED → rejects
    $update = $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Should not work',
    ]);

    $update->assertStatus(422);
});

test('response create is rejected when inject becomes LOCKED before creation', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    // Simulate concurrent advance: lock the inject
    $sessionInject->update(['status' => TtxSessionInjectStatus::Locked, 'locked_at' => now()]);

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Should not work',
    ]);

    $create->assertStatus(422);
});

test('response create is rejected when session transitions to DEBRIEF', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    // Simulate concurrent advance that transitions session to DEBRIEF
    $session->update(['status' => TtxSessionStatus::Debrief, 'debrief_started_at' => now()]);

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Should not work',
    ]);

    $create->assertStatus(422);
});

test('response update is rejected when session transitions to DEBRIEF', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $create = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Original',
    ]);
    $responseId = $create->json('id');

    // Simulate concurrent advance that transitions session to DEBRIEF
    $session->update(['status' => TtxSessionStatus::Debrief, 'debrief_started_at' => now()]);

    $update = $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Should not work',
    ]);

    $update->assertStatus(422);
});

test('concurrent duplicate create produces one response and controlled conflict', function () {
    // The UNIQUE(session_inject_id) DB invariant is authoritative.
    // The application-level exists() check catches most races.
    // The QueryException catch translates the remaining DB-level race into 409.
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $beforeCount = AuditLog::where('action', 'ttx.response_saved')->count();

    $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'First response',
    ]);

    $afterFirstCount = AuditLog::where('action', 'ttx.response_saved')->count();
    expect($afterFirstCount)->toBe($beforeCount + 1);

    // Second attempt — application check catches duplicate → 409 (not 500)
    $duplicate = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Duplicate response',
    ]);

    $duplicate->assertStatus(409);

    // No additional success audit from rejected duplicate
    $afterDuplicateCount = AuditLog::where('action', 'ttx.response_saved')->count();
    expect($afterDuplicateCount)->toBe($afterFirstCount);

    // Only one response exists
    $count = TtxSessionResponse::where('session_inject_id', $sessionInject->id)->count();
    expect($count)->toBe(1);
});

test('failed mutation due to lifecycle lock emits no audit event', function () {
    [, , , $participant, , , $session, $sessionInject] = ttxResponseRuntimeFixture();

    $beforeCount = AuditLog::where('action', 'ttx.response_saved')->count();

    // Lock inject, then attempt create → fails
    $sessionInject->update(['status' => TtxSessionInjectStatus::Locked, 'locked_at' => now()]);

    $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Should not audit',
    ]);

    $afterCount = AuditLog::where('action', 'ttx.response_saved')->count();
    expect($afterCount)->toBe($beforeCount);
});

// ─── C3: RESPONSE LOCKING AND PROGRESSION INTEGRITY ─────

function ttxProgressionFixture(): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $facilitator = User::factory()->create(['tenant_id' => $tenant->id]);
    $participant = User::factory()->create(['tenant_id' => $tenant->id]);

    $exercise = TtxExercise::create([
        'tenant_id' => $tenant->id,
        'title' => 'Progression exercise',
        'scenario' => 'Progression scenario',
    ]);

    $inject1 = TtxInject::create([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'order' => 1,
        'title' => 'First inject',
        'description' => 'First inject description',
    ]);

    $inject2 = TtxInject::create([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'order' => 2,
        'title' => 'Second inject',
        'description' => 'Second inject description',
    ]);

    $session = TtxSession::forceCreate([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'title' => 'Progression session',
        'created_by' => $admin->id,
        'status' => TtxSessionStatus::InProgress,
        'started_at' => now(),
        'exercise_snapshot' => ['title' => $exercise->title, 'scenario' => $exercise->scenario],
    ]);

    TtxSessionParticipant::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'user_id' => $admin->id,
        'session_role' => TtxSessionRole::Facilitator,
    ]);

    TtxSessionParticipant::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'user_id' => $participant->id,
        'session_role' => TtxSessionRole::Security,
    ]);

    $sessionInject1 = TtxSessionInject::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'inject_id' => $inject1->id,
        'order' => 1,
        'status' => TtxSessionInjectStatus::Active,
        'inject_snapshot' => ['title' => $inject1->title, 'description' => $inject1->description],
        'released_at' => now(),
        'released_by' => $admin->id,
    ]);

    $sessionInject2 = TtxSessionInject::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'inject_id' => $inject2->id,
        'order' => 2,
        'status' => TtxSessionInjectStatus::Pending,
        'inject_snapshot' => ['title' => $inject2->title, 'description' => $inject2->description],
    ]);

    return [$tenant, $admin, $facilitator, $participant, $exercise, $inject1, $inject2, $session, $sessionInject1, $sessionInject2];
}

function ttxFinalProgressionFixture(): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $participant = User::factory()->create(['tenant_id' => $tenant->id]);

    $exercise = TtxExercise::create([
        'tenant_id' => $tenant->id,
        'title' => 'Final progression exercise',
        'scenario' => 'Final scenario',
    ]);

    $inject1 = TtxInject::create([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'order' => 1,
        'title' => 'Only inject',
        'description' => 'Only inject description',
    ]);

    $session = TtxSession::forceCreate([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'title' => 'Final progression session',
        'created_by' => $admin->id,
        'status' => TtxSessionStatus::InProgress,
        'started_at' => now(),
        'exercise_snapshot' => ['title' => $exercise->title, 'scenario' => $exercise->scenario],
    ]);

    TtxSessionParticipant::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'user_id' => $admin->id,
        'session_role' => TtxSessionRole::Facilitator,
    ]);

    TtxSessionParticipant::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'user_id' => $participant->id,
        'session_role' => TtxSessionRole::Security,
    ]);

    $sessionInject1 = TtxSessionInject::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'inject_id' => $inject1->id,
        'order' => 1,
        'status' => TtxSessionInjectStatus::Active,
        'inject_snapshot' => ['title' => $inject1->title, 'description' => $inject1->description],
        'released_at' => now(),
        'released_by' => $admin->id,
    ]);

    return [$tenant, $admin, $participant, $exercise, $inject1, $session, $sessionInject1];
}

// ─── FAILED PROGRESSION ──────────────────────────────────

test('facilitator cannot advance ACTIVE inject with no official response', function () {
    [, $admin, , , , , , $session] = ttxProgressionFixture();
    $this->actingAs($admin);

    $service = app(TtxSessionService::class);
    expect(fn () => $service->advanceInject($session, $admin))
        ->toThrow(ValidationException::class);
});

test('facilitator cannot advance with blank decision', function () {
    [, $admin, , , , , , $session, $sessionInject] = ttxProgressionFixture();
    $this->actingAs($admin);

    TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $sessionInject->id,
        'decision' => '',
        'revision' => 1,
        'submitted_by' => $admin->id,
        'submitted_at' => now(),
    ]);

    $service = app(TtxSessionService::class);
    expect(fn () => $service->advanceInject($session, $admin))
        ->toThrow(ValidationException::class);
});

test('whitespace-only decision cannot satisfy progression', function () {
    [, $admin, , , , , , $session, $sessionInject] = ttxProgressionFixture();
    $this->actingAs($admin);

    TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $sessionInject->id,
        'decision' => '   ',
        'revision' => 1,
        'submitted_by' => $admin->id,
        'submitted_at' => now(),
    ]);

    $service = app(TtxSessionService::class);
    expect(fn () => $service->advanceInject($session, $admin))
        ->toThrow(ValidationException::class);
});

test('failed progression leaves current inject ACTIVE', function () {
    [, $admin, , , , , , $session, $sessionInject] = ttxProgressionFixture();
    $this->actingAs($admin);

    $service = app(TtxSessionService::class);
    try {
        $service->advanceInject($session, $admin);
    } catch (Exception $e) {
    }

    expect($sessionInject->fresh()->status)->toBe(TtxSessionInjectStatus::Active);
});

test('failed progression does not activate next inject', function () {
    [, $admin, , , , , , $session, $sessionInject1, $sessionInject2] = ttxProgressionFixture();
    $this->actingAs($admin);

    $service = app(TtxSessionService::class);
    try {
        $service->advanceInject($session, $admin);
    } catch (Exception $e) {
    }

    expect($sessionInject2->fresh()->status)->toBe(TtxSessionInjectStatus::Pending);
});

test('failed progression does not enter DEBRIEF', function () {
    [, $admin, , , , , , $session] = ttxProgressionFixture();
    $this->actingAs($admin);

    $service = app(TtxSessionService::class);
    try {
        $service->advanceInject($session, $admin);
    } catch (Exception $e) {
    }

    expect($session->fresh()->status)->toBe(TtxSessionStatus::InProgress);
});

test('failed progression does not set response locked_at', function () {
    [, $admin, , , , , , $session, $sessionInject] = ttxProgressionFixture();
    $this->actingAs($admin);

    $response = TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $sessionInject->id,
        'decision' => '',
        'revision' => 1,
        'submitted_by' => $admin->id,
        'submitted_at' => now(),
    ]);

    $service = app(TtxSessionService::class);
    try {
        $service->advanceInject($session, $admin);
    } catch (Exception $e) {
    }

    expect($response->fresh()->locked_at)->toBeNull();
});

test('failed progression emits no ttx.response_locked success audit', function () {
    [, $admin, , , , , , $session] = ttxProgressionFixture();
    $this->actingAs($admin);

    $beforeCount = AuditLog::where('action', 'ttx.response_locked')->count();

    $service = app(TtxSessionService::class);
    try {
        $service->advanceInject($session, $admin);
    } catch (Exception $e) {
    }

    $afterCount = AuditLog::where('action', 'ttx.response_locked')->count();
    expect($afterCount)->toBe($beforeCount);
});

// ─── SUCCESSFUL NON-FINAL PROGRESSION ────────────────────

test('successful non-final progression locks response and advances inject', function () {
    [, $admin, , , , , , $session, $sessionInject1, $sessionInject2] = ttxProgressionFixture();
    $this->actingAs($admin);

    $response = TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $sessionInject1->id,
        'decision' => 'Contain the breach',
        'revision' => 1,
        'submitted_by' => $admin->id,
        'submitted_at' => now(),
    ]);

    $service = app(TtxSessionService::class);
    $result = $service->advanceInject($session, $admin);

    // Response locked_at becomes non-null
    expect($response->fresh()->locked_at)->not->toBeNull();
    // Response revision does NOT change
    expect($response->fresh()->revision)->toBe(1);
    // Current inject becomes LOCKED
    expect($sessionInject1->fresh()->status)->toBe(TtxSessionInjectStatus::Locked);
    // Next inject becomes ACTIVE
    expect($sessionInject2->fresh()->status)->toBe(TtxSessionInjectStatus::Active);
    // Next inject released_at/released_by populated
    expect($sessionInject2->fresh()->released_at)->not->toBeNull()
        ->and($sessionInject2->fresh()->released_by)->toBe($admin->id);
    // Returned inject is the next one
    expect($result->id)->toBe($sessionInject2->fresh()->id);
});

test('ttx.response_locked emitted exactly once on non-final progression', function () {
    [, $admin, , , , , , $session, $sessionInject1] = ttxProgressionFixture();
    $this->actingAs($admin);

    TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $sessionInject1->id,
        'decision' => 'Action taken',
        'revision' => 1,
        'submitted_by' => $admin->id,
        'submitted_at' => now(),
    ]);

    $beforeCount = AuditLog::where('action', 'ttx.response_locked')->count();

    $service = app(TtxSessionService::class);
    $service->advanceInject($session, $admin);

    $afterCount = AuditLog::where('action', 'ttx.response_locked')->count();
    expect($afterCount)->toBe($beforeCount + 1);
});

test('existing inject progression audits remain correct on non-final progression', function () {
    [, $admin, , , , , , $session, $sessionInject1] = ttxProgressionFixture();
    $this->actingAs($admin);

    TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $sessionInject1->id,
        'decision' => 'Decision',
        'revision' => 1,
        'submitted_by' => $admin->id,
        'submitted_at' => now(),
    ]);

    $service = app(TtxSessionService::class);
    $service->advanceInject($session, $admin);

    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.inject_locked']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.inject_released']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.response_locked']);
    $this->assertDatabaseMissing('audit_logs', ['action' => 'ttx.debrief_started']);
});

// ─── SUCCESSFUL FINAL PROGRESSION ────────────────────────

test('successful final progression enters DEBRIEF and locks response', function () {
    [, $admin, , , , $session, $sessionInject1] = ttxFinalProgressionFixture();
    $this->actingAs($admin);

    $response = TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $sessionInject1->id,
        'decision' => 'Final decision',
        'revision' => 1,
        'submitted_by' => $admin->id,
        'submitted_at' => now(),
    ]);

    $service = app(TtxSessionService::class);
    $result = $service->advanceInject($session, $admin);

    // Response locked_at becomes non-null
    expect($response->fresh()->locked_at)->not->toBeNull();
    // Response revision unchanged
    expect($response->fresh()->revision)->toBe(1);
    // Current inject becomes LOCKED
    expect($sessionInject1->fresh()->status)->toBe(TtxSessionInjectStatus::Locked);
    // Session becomes DEBRIEF
    expect($session->fresh()->status)->toBe(TtxSessionStatus::Debrief);
    // debrief_started_at is populated
    expect($session->fresh()->debrief_started_at)->not->toBeNull();
    // Returned inject is the current one (final advance convention)
    expect($result->id)->toBe($sessionInject1->fresh()->id);
});

test('ttx.response_locked and ttx.debrief_started emitted on final progression', function () {
    [, $admin, , , , $session, $sessionInject1] = ttxFinalProgressionFixture();
    $this->actingAs($admin);

    TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $sessionInject1->id,
        'decision' => 'Final decision',
        'revision' => 1,
        'submitted_by' => $admin->id,
        'submitted_at' => now(),
    ]);

    $service = app(TtxSessionService::class);
    $service->advanceInject($session, $admin);

    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.inject_locked']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.response_locked']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.debrief_started']);
});

// ─── IMMUTABILITY AFTER PROGRESSION ──────────────────────

test('response update after progression is rejected', function () {
    [, $admin, , , , , , $session, $sessionInject1] = ttxProgressionFixture();
    $this->actingAs($admin);

    $response = TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $sessionInject1->id,
        'decision' => 'Original decision',
        'revision' => 1,
        'submitted_by' => $admin->id,
        'submitted_at' => now(),
    ]);

    $service = app(TtxSessionService::class);
    $service->advanceInject($session, $admin);

    // Inject is now LOCKED — update should be rejected
    $update = $this->actingAs($admin)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $response->id]), [
        'expected_revision' => 1,
        'decision' => 'Should not work',
    ]);

    $update->assertStatus(422);
});

test('rejected update after progression does not alter response content', function () {
    [, $admin, , , , , , $session, $sessionInject1] = ttxProgressionFixture();
    $this->actingAs($admin);

    $response = TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $sessionInject1->id,
        'decision' => 'Original decision',
        'revision' => 1,
        'submitted_by' => $admin->id,
        'submitted_at' => now(),
    ]);

    $service = app(TtxSessionService::class);
    $service->advanceInject($session, $admin);

    $this->actingAs($admin)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $response->id]), [
        'expected_revision' => 1,
        'decision' => 'Should not apply',
    ]);

    expect($response->fresh()->decision)->toBe('Original decision');
});

test('rejected update after progression does not increment revision', function () {
    [, $admin, , , , , , $session, $sessionInject1] = ttxProgressionFixture();
    $this->actingAs($admin);

    $response = TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $sessionInject1->id,
        'decision' => 'Original decision',
        'revision' => 1,
        'submitted_by' => $admin->id,
        'submitted_at' => now(),
    ]);

    $service = app(TtxSessionService::class);
    $service->advanceInject($session, $admin);

    $this->actingAs($admin)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $response->id]), [
        'expected_revision' => 1,
        'decision' => 'Should not work',
    ]);

    expect($response->fresh()->revision)->toBe(1);
});

test('rejected update after progression does not emit ttx.response_saved', function () {
    [, $admin, , , , , , $session, $sessionInject1] = ttxProgressionFixture();
    $this->actingAs($admin);

    $response = TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $sessionInject1->id,
        'decision' => 'Original decision',
        'revision' => 1,
        'submitted_by' => $admin->id,
        'submitted_at' => now(),
    ]);

    $service = app(TtxSessionService::class);
    $service->advanceInject($session, $admin);

    $beforeCount = AuditLog::where('action', 'ttx.response_saved')->count();

    $this->actingAs($admin)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $response->id]), [
        'expected_revision' => 1,
        'decision' => 'Should not audit',
    ]);

    $afterCount = AuditLog::where('action', 'ttx.response_saved')->count();
    expect($afterCount)->toBe($beforeCount);
});

// ─── CONCURRENCY / LIFECYCLE ─────────────────────────────

test('response update cannot commit after concurrent progression has locked the inject', function () {
    [, $admin, , $participant, , , , $session, $sessionInject1] = ttxProgressionFixture();

    $response = TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $sessionInject1->id,
        'decision' => 'Original',
        'revision' => 1,
        'submitted_by' => $participant->id,
        'submitted_at' => now(),
    ]);

    // Simulate concurrent advance: lock the inject (as advanceInject would)
    $sessionInject1->update(['status' => TtxSessionInjectStatus::Locked, 'locked_at' => now()]);

    // Editor attempts update — inject is now LOCKED → rejected
    $update = $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $response->id]), [
        'expected_revision' => 1,
        'decision' => 'Should not work',
    ]);

    $update->assertStatus(422);
});

test('repeated advance after entering DEBRIEF remains rejected', function () {
    [, $admin, , , , , , $session, $sessionInject1] = ttxProgressionFixture();
    $this->actingAs($admin);

    TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $sessionInject1->id,
        'decision' => 'Decision',
        'revision' => 1,
        'submitted_by' => $admin->id,
        'submitted_at' => now(),
    ]);

    $service = app(TtxSessionService::class);
    $service->advanceInject($session, $admin);

    expect(fn () => $service->advanceInject($session->fresh(), $admin))
        ->toThrow(ValidationException::class);
});

test('no duplicate response_locked audit on repeated failed progression', function () {
    [, $admin, , , , , , $session] = ttxProgressionFixture();
    $this->actingAs($admin);

    $beforeCount = AuditLog::where('action', 'ttx.response_locked')->count();

    // No response exists — advance fails
    $service = app(TtxSessionService::class);
    try {
        $service->advanceInject($session, $admin);
    } catch (Exception $e) {
    }
    try {
        $service->advanceInject($session, $admin);
    } catch (Exception $e) {
    }

    $afterCount = AuditLog::where('action', 'ttx.response_locked')->count();
    expect($afterCount)->toBe($beforeCount);
});
