<?php

use App\Enums\TtxSessionInjectStatus;
use App\Enums\TtxSessionStatus;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\User;
use App\Services\TtxSessionService;
use Illuminate\Support\Facades\Gate;

function preparationP3Fixture(): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $facilitator = $admin;
    $participant = User::factory()->create(['tenant_id' => $tenant->id]);
    $exercise = TtxExercise::create([
        'tenant_id' => $tenant->id,
        'title' => 'Latihan persiapan P3',
        'scenario' => 'Skenario rahasia P3',
    ]);
    $exercise = attachTtxTestPlaybook($exercise);
    TtxInject::create([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'order' => 1,
        'title' => 'Inject rahasia P3',
        'description' => 'Konten masa depan P3',
    ]);
    $service = app(TtxSessionService::class);
    $session = $service->create($admin, $exercise, 'Sesi persiapan P3');
    $team = $service->createTeam($admin, $session, 'Security / SOC');
    $service->updateTeamResponsibilities($admin, $session, $team->id, 'Validasi alert dan koordinasikan respons insiden.');
    $service->assignParticipant($admin, $session, $participant, $team);

    return [$tenant, $admin, $facilitator, $participant, $session, $service];
}

test('p3 valid draft session becomes ready and returns authoritative preparation state with audit', function () {
    [$tenant, $admin, , , $session] = preparationP3Fixture();
    $foreignTenant = Tenant::factory()->create();

    $response = $this->actingAs($admin)->postJson(route('tenant.ttx.sessions.ready', $session), [
        'status' => 'completed',
        'tenant_id' => $foreignTenant->id,
        'readiness' => ['can_mark_ready' => false],
    ]);

    $response->assertOk()
        ->assertJsonPath('session.status', 'ready')
        ->assertJsonPath('readiness.can_mark_ready', false)
        ->assertJsonPath('permissions.can_mark_ready', false)
        ->assertJsonPath('permissions.can_open_console', true)
        ->assertJsonMissingPath('exercise_snapshot')
        ->assertJsonMissingPath('injects')
        ->assertJsonMissingPath('responses');

    expect($session->fresh()->status)->toBe(TtxSessionStatus::Ready)
        ->and($session->fresh()->tenant_id)->toBe($tenant->id);
    $audit = AuditLog::where('action', 'ttx.session_ready')->where('subject_id', (string) $session->id)->sole();
    expect($audit->tenant_id)->toBe($tenant->id)
        ->and($audit->actor_user_id)->toBe($admin->id);
});

test('p3 mark ready blocks each missing readiness prerequisite without partial mutation', function (string $invalid) {
    [, $admin, , , $session] = preparationP3Fixture();

    match ($invalid) {
        'snapshot' => $session->update(['exercise_snapshot' => []]),
        'teams' => tap($session->participants()->delete(), fn () => $session->teams()->delete()),
        'participant' => $session->participants()->delete(),
        'injects' => $session->injects()->delete(),
        'inject_state' => $session->injects()->update(['status' => TtxSessionInjectStatus::Active]),
    };

    $this->actingAs($admin)
        ->postJson(route('tenant.ttx.sessions.ready', $session))
        ->assertUnprocessable();

    expect($session->fresh()->status)->toBe(TtxSessionStatus::Draft)
        ->and(AuditLog::where('action', 'ttx.session_ready')->where('subject_id', (string) $session->id)->exists())->toBeFalse();
})->with(['snapshot', 'teams', 'participant', 'injects', 'inject_state']);

test('p3 unauthorized actors cannot mark a session ready', function (string $actorType) {
    [$tenant, $admin, , $participant, $session] = preparationP3Fixture();
    $actor = match ($actorType) {
        'learner' => $participant,
        'inactive_admin' => tap($admin)->update(['is_active' => false]),
        'cross_tenant_admin' => User::factory()->tenantAdmin()->create([
            'tenant_id' => Tenant::factory()->create()->id,
        ]),
    };

    $this->actingAs($actor->fresh())
        ->postJson(route('tenant.ttx.sessions.ready', $session), ['tenant_id' => $tenant->id])
        ->assertForbidden();

    expect($session->fresh()->status)->toBe(TtxSessionStatus::Draft)
        ->and(AuditLog::where('action', 'ttx.session_ready')->where('subject_id', (string) $session->id)->exists())->toBeFalse();
})->with(['learner', 'inactive_admin', 'cross_tenant_admin']);

test('p3 mark ready rejects every non-draft lifecycle state', function (string $status) {
    [, $admin, , , $session] = preparationP3Fixture();
    $session->update(['status' => $status]);

    $this->actingAs($admin)
        ->postJson(route('tenant.ttx.sessions.ready', $session))
        ->assertUnprocessable();

    expect($session->fresh()->status->value)->toBe($status)
        ->and(AuditLog::where('action', 'ttx.session_ready')->where('subject_id', (string) $session->id)->exists())->toBeFalse();
})->with(['ready', 'in_progress', 'debrief', 'completed']);

test('p3 preparation read model exposes authoritative readiness and permissions', function () {
    [, $admin, $facilitator, , $session, $service] = preparationP3Fixture();

    $payload = $service->preparationReadModel($admin, $session->fresh());

    expect($payload['readiness'])->toMatchArray([
        'has_exercise_snapshot' => true,
        'has_facilitator' => true,
        'has_non_facilitator_participant' => true,
        'has_injects' => true,
        'all_injects_pending' => true,
        'can_mark_ready' => true,
    ])->and($payload['permissions'])->toMatchArray([
        'can_manage_roster' => true,
        'can_mark_ready' => true,
        'can_open_console' => true,
    ])->and($payload['exercise_context']['scenario'])->toBe('Skenario rahasia P3')
        ->and(json_encode($payload))->not->toContain('Inject rahasia P3', 'Konten masa depan P3');

    $session->injects()->update(['status' => TtxSessionInjectStatus::Locked]);
    $invalid = $service->preparationReadModel($admin, $session->fresh());
    expect($invalid['readiness']['all_injects_pending'])->toBeFalse()
        ->and($invalid['readiness']['can_mark_ready'])->toBeFalse()
        ->and($invalid['permissions']['can_mark_ready'])->toBeFalse();

    $otherAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $session->tenant_id]);
    expect(Gate::forUser($otherAdmin)->allows('facilitate', $session->fresh()))->toBeFalse();
});

test('p3 readiness flags identify the authoritative missing prerequisite', function (string $invalid, string $field) {
    [, $admin, , , $session, $service] = preparationP3Fixture();

    match ($invalid) {
        'snapshot' => $session->update(['exercise_snapshot' => []]),
        'teams' => tap($session->participants()->delete(), fn () => $session->teams()->delete()),
        'participant' => $session->participants()->delete(),
        'injects' => $session->injects()->delete(),
        'inject_state' => $session->injects()->update(['status' => TtxSessionInjectStatus::Active]),
    };

    $payload = $service->preparationReadModel($admin, $session->fresh());

    expect($payload['readiness'][$field])->toBeFalse()
        ->and($payload['readiness']['can_mark_ready'])->toBeFalse()
        ->and($payload['permissions']['can_mark_ready'])->toBeFalse();
})->with([
    ['snapshot', 'has_exercise_snapshot'],
    ['teams', 'has_teams'],
    ['participant', 'has_non_facilitator_participant'],
    ['injects', 'has_injects'],
    ['inject_state', 'all_injects_pending'],
]);
