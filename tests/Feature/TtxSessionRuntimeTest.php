<?php

use App\Enums\TtxSessionInjectStatus;
use App\Enums\TtxSessionStatus;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxSession;
use App\Models\TtxSessionResponse;
use App\Models\User;
use App\Services\TtxSessionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

function ttxRuntimeFixture(): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $participant = User::factory()->create(['tenant_id' => $tenant->id]);
    $exercise = TtxExercise::create([
        'tenant_id' => $tenant->id,
        'title' => 'Runtime exercise',
        'scenario' => 'Original scenario',
        'objectives' => 'Protect services',
    ]);
    $exercise = attachTtxTestPlaybook($exercise);
    $inject = TtxInject::create([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'order' => 1,
        'title' => 'Initial inject',
        'description' => 'Initial description',
    ]);

    return [$tenant, $admin, $participant, $exercise, $inject];
}

function assignRuntimeLearner(TtxSessionService $service, User $admin, TtxSession $session, User $learner): void
{
    $team = $session->teams()->first() ?? $service->createTeam($admin, $session, 'Security / SOC');
    $service->updateTeamResponsibilities($admin, $session, $team->id, 'Validasi alert dan koordinasikan respons insiden.');
    $service->assignParticipant($admin, $session, $learner, $team);
}

test('facilitator can create, prepare, start, and progress a session from immutable snapshots', function () {
    [, $admin, $participant, $exercise, $inject] = ttxRuntimeFixture();
    $this->actingAs($admin);
    $service = app(TtxSessionService::class);

    $session = $service->create($admin, $exercise, 'Live response');
    assignRuntimeLearner($service, $admin, $session, $participant);

    $exercise->update(['scenario' => 'Changed after creation']);
    $inject->update(['description' => 'Changed after creation']);

    $session = $service->markReady($admin, $session);
    expect($session->status)->toBe(TtxSessionStatus::Ready);
    expect($service->start($admin, $session)->status)->toBe(TtxSessionStatus::InProgress);

    $released = $session->fresh()->injects()->first();
    expect($released->status)->toBe(TtxSessionInjectStatus::Active)
        ->and($released->inject_snapshot['description'])->toBe('Initial description')
        ->and($released->released_at)->not->toBeNull()
        ->and($released->released_by)->toBe($admin->id)
        ->and($session->fresh()->exercise_snapshot['scenario'])->toBe('Original scenario');

    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.session_created']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.session_participant_assigned']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.session_ready']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.session_started']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.inject_released']);
});

test('readiness rejects missing non-facilitator, active or locked injects, and started sessions', function () {
    [, $admin, , $exercise] = ttxRuntimeFixture();
    $this->actingAs($admin);
    $service = app(TtxSessionService::class);
    $session = $service->create($admin, $exercise, 'Readiness checks');

    expect(fn () => $service->markReady($admin, $session))->toThrow(ValidationException::class);

    $participant = User::factory()->create(['tenant_id' => $exercise->tenant_id]);
    assignRuntimeLearner($service, $admin, $session, $participant);
    $sessionInject = $session->fresh()->injects()->first();
    $sessionInject->update(['status' => TtxSessionInjectStatus::Active]);
    expect(fn () => $service->markReady($admin, $session->fresh()))->toThrow(ValidationException::class);
    $sessionInject->update(['status' => TtxSessionInjectStatus::Locked]);
    expect(fn () => $service->markReady($admin, $session->fresh()))->toThrow(ValidationException::class);
    $sessionInject->update(['status' => TtxSessionInjectStatus::Pending]);
    $session->update(['started_at' => now()]);
    expect(fn () => $service->markReady($admin, $session->fresh()))->toThrow(ValidationException::class);
});

test('only creator tenant admin can start', function () {
    [, $admin, $participant, $exercise] = ttxRuntimeFixture();
    $otherAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $exercise->tenant_id]);
    $this->actingAs($admin);
    $service = app(TtxSessionService::class);
    $session = $service->create($admin, $exercise, 'Facilitator authorization');
    assignRuntimeLearner($service, $admin, $session, $participant);
    $service->markReady($admin, $session);

    expect(fn () => $service->start($otherAdmin, $session))->toThrow(HttpException::class);
    expect(fn () => $service->start($participant, $session))->toThrow(HttpException::class);
    expect($service->start($admin, $session)->status)->toBe(TtxSessionStatus::InProgress);
    expect($session->fresh()->injects()->where('status', TtxSessionInjectStatus::Active)->count())->toBe(1);
});

test('a learner cannot become facilitator or start through HTTP', function () {
    [, $admin, $participant, $exercise] = ttxRuntimeFixture();
    $facilitator = User::factory()->create(['tenant_id' => $exercise->tenant_id]);
    $this->actingAs($admin);
    $service = app(TtxSessionService::class);
    $session = $service->create($admin, $exercise, 'Ordinary facilitator');
    assignRuntimeLearner($service, $admin, $session, $participant);
    $service->markReady($admin, $session);

    $this->actingAs($facilitator)->post(route('tenant.ttx.sessions.start', $session))->assertForbidden();
    expect($service->start($admin, $session->fresh())->status)->toBe(TtxSessionStatus::InProgress);
});

test('assignment is limited to draft sessions and active learners', function () {
    [, $admin, $participant, $exercise] = ttxRuntimeFixture();
    $inactive = User::factory()->create(['tenant_id' => $exercise->tenant_id, 'is_active' => false]);
    $this->actingAs($admin);
    $service = app(TtxSessionService::class);
    $session = $service->create($admin, $exercise, 'Assignment lifecycle');
    assignRuntimeLearner($service, $admin, $session, $participant);
    expect(fn () => assignRuntimeLearner($service, $admin, $session, $inactive))
        ->toThrow(HttpException::class);
    $service->markReady($admin, $session);
    $lateParticipant = User::factory()->create(['tenant_id' => $exercise->tenant_id]);
    expect(fn () => assignRuntimeLearner($service, $admin, $session, $lateParticipant))
        ->toThrow(HttpException::class);
    $service->start($admin, $session);
    expect(fn () => assignRuntimeLearner($service, $admin, $session, $lateParticipant))
        ->toThrow(HttpException::class);
});

test('start activates the first inject and final advance enters debrief', function () {
    [, $admin, , $exercise] = ttxRuntimeFixture();
    TtxInject::create([
        'tenant_id' => $exercise->tenant_id,
        'exercise_id' => $exercise->id,
        'order' => 2,
        'title' => 'Final inject',
        'description' => 'Final description',
    ]);
    $this->actingAs($admin);
    $service = app(TtxSessionService::class);
    $participant = User::factory()->create(['tenant_id' => $exercise->tenant_id]);
    $session = $service->create($admin, $exercise, 'Debrief transition');
    assignRuntimeLearner($service, $admin, $session, $participant);
    $session = $service->markReady($admin, $session);
    $session = $service->start($admin, $session);

    expect($session->fresh()->injects()->where('status', TtxSessionInjectStatus::Active)->count())->toBe(1)
        ->and($session->fresh()->injects()->where('status', TtxSessionInjectStatus::Active)->first()->released_by)->toBe($admin->id)
        ->and($session->fresh()->injects()->where('status', TtxSessionInjectStatus::Active)->first()->released_at)->not->toBeNull();

    // Create response for first inject before advancing
    $firstInject = $session->fresh()->injects()->where('status', TtxSessionInjectStatus::Active)->first();
    TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $firstInject->id,
        'decision' => 'First decision',
        'revision' => 1,
        'submitted_by' => $admin->id,
        'submitted_at' => now(),
    ]);

    $service->advanceInject($session, $admin);

    // Create response for second inject before advancing
    $secondInject = $session->fresh()->injects()->where('status', TtxSessionInjectStatus::Active)->first();
    TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $secondInject->id,
        'decision' => 'Second decision',
        'revision' => 1,
        'submitted_by' => $admin->id,
        'submitted_at' => now(),
    ]);

    $service->advanceInject($session->fresh(), $admin);

    expect($session->fresh()->status)->toBe(TtxSessionStatus::Debrief);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.inject_locked']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.debrief_started']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.response_locked']);
    expect(fn () => $service->advanceInject($session->fresh(), $admin))
        ->toThrow(ValidationException::class);
});

test('participant read model cannot see pending inject contents while facilitator can', function () {
    [, $admin, $participant, $exercise] = ttxRuntimeFixture();
    $this->actingAs($admin);
    $service = app(TtxSessionService::class);

    $session = $service->create($admin, $exercise, 'Safe read model');
    assignRuntimeLearner($service, $admin, $session, $participant);

    $facilitatorModel = $service->readModel($admin, $session);
    $participantModel = $service->readModel($participant, $session);

    expect($facilitatorModel['injects'][0]['snapshot'])->not->toBeNull()
        ->and($participantModel['injects'])->toBeEmpty()
        ->and(json_encode($participantModel))->not->toContain('Initial inject')
        ->and(json_encode($participantModel))->not->toContain('Initial description');
});

test('wrong tenant and wrong role cannot manage a session', function () {
    [, $admin, $participant, $exercise] = ttxRuntimeFixture();
    $otherTenant = Tenant::factory()->create();
    $otherAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $otherTenant->id]);
    $this->actingAs($admin);
    $service = app(TtxSessionService::class);
    $session = $service->create($admin, $exercise, 'Authorization');
    assignRuntimeLearner($service, $admin, $session, $participant);

    expect(fn () => assignRuntimeLearner($service, $otherAdmin, $session, $otherAdmin))
        ->toThrow(HttpException::class);
    expect(fn () => $service->advanceInject($session, $participant))
        ->toThrow(HttpException::class);
    expect(Gate::forUser($participant)->allows('manage', $session))->toBeFalse()
        ->and($this->actingAs($participant)->get(route('tenant.ttx.sessions.show', $session))->status())->toBe(200)
        ->and($this->actingAs($participant)->post(route('tenant.ttx.sessions.start', $session))->status())->toBe(403);
});

test('session cannot start before readiness', function () {
    [, $admin, , $exercise] = ttxRuntimeFixture();
    $this->actingAs($admin);
    $service = app(TtxSessionService::class);
    $session = $service->create($admin, $exercise, 'Not ready');

    expect(fn () => $service->start($admin, $session))
        ->toThrow(ValidationException::class);
});

test('participant HTTP access is outside tenant admin middleware but runtime actions remain forbidden', function () {
    [, $admin, $participant, $exercise] = ttxRuntimeFixture();
    $session = app(TtxSessionService::class)->create($admin, $exercise, 'HTTP access');
    assignRuntimeLearner(app(TtxSessionService::class), $admin, $session, $participant);

    expect($this->actingAs($participant)->get(route('tenant.ttx.sessions.show', $session))->status())
        ->toBe(200)
        ->and($this->actingAs($participant)->post(route('tenant.ttx.sessions.ready', $session))->status())
        ->toBe(403);
});
