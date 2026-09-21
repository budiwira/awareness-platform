<?php

use App\Enums\TtxSessionInjectStatus;
use App\Enums\TtxSessionRole;
use App\Enums\TtxSessionStatus;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\User;
use App\Services\TtxSessionService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Gate;

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
    $inject = TtxInject::create([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'order' => 1,
        'title' => 'Initial inject',
        'description' => 'Initial description',
    ]);

    return [$tenant, $admin, $participant, $exercise, $inject];
}

test('facilitator can create, prepare, start, and progress a session from immutable snapshots', function () {
    [, $admin, $participant, $exercise, $inject] = ttxRuntimeFixture();
    $this->actingAs($admin);
    $service = app(TtxSessionService::class);

    $session = $service->create($admin, $exercise, 'Live response');
    $service->assignParticipant($admin, $session, $admin, TtxSessionRole::Facilitator);
    $service->assignParticipant($admin, $session, $participant, TtxSessionRole::Security);

    $exercise->update(['scenario' => 'Changed after creation']);
    $inject->update(['description' => 'Changed after creation']);

    $session = $service->markReady($session);
    expect($session->status)->toBe(TtxSessionStatus::Ready);
    expect($service->start($session)->status)->toBe(TtxSessionStatus::InProgress);

    $released = $service->advanceInject($session->fresh(), $admin);
    expect($released->status)->toBe(TtxSessionInjectStatus::Active)
        ->and($released->inject_snapshot['description'])->toBe('Initial description')
        ->and($session->fresh()->exercise_snapshot['scenario'])->toBe('Original scenario');

    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.session_created']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.session_participant_assigned']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.session_ready']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.session_started']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.session_inject_released']);
});

test('participant read model cannot see pending inject contents while facilitator can', function () {
    [, $admin, $participant, $exercise] = ttxRuntimeFixture();
    $this->actingAs($admin);
    $service = app(TtxSessionService::class);

    $session = $service->create($admin, $exercise, 'Safe read model');
    $service->assignParticipant($admin, $session, $admin, TtxSessionRole::Facilitator);
    $service->assignParticipant($admin, $session, $participant, TtxSessionRole::Security);

    $facilitatorModel = $service->readModel($admin, $session);
    $participantModel = $service->readModel($participant, $session);

    expect($facilitatorModel['injects'][0]['snapshot'])->not->toBeNull()
        ->and($participantModel['injects'][0]['snapshot'])->toBeNull();
});

test('wrong tenant and wrong role cannot manage a session', function () {
    [, $admin, $participant, $exercise] = ttxRuntimeFixture();
    $otherTenant = Tenant::factory()->create();
    $otherAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $otherTenant->id]);
    $this->actingAs($admin);
    $service = app(TtxSessionService::class);
    $session = $service->create($admin, $exercise, 'Authorization');
    $service->assignParticipant($admin, $session, $participant, TtxSessionRole::Security);

    expect(fn () => $service->assignParticipant($otherAdmin, $session, $otherAdmin, TtxSessionRole::Facilitator))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(Gate::forUser($participant)->allows('manage', $session))->toBeFalse();
});

test('session cannot start before readiness', function () {
    [, $admin, , $exercise] = ttxRuntimeFixture();
    $this->actingAs($admin);
    $service = app(TtxSessionService::class);
    $session = $service->create($admin, $exercise, 'Not ready');

    expect(fn () => $service->start($session))
        ->toThrow(ValidationException::class);
});
