<?php

use App\Enums\TtxSessionStatus;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\User;
use App\Services\TtxSessionService;
use Symfony\Component\HttpKernel\Exception\HttpException;

function preparationP2Fixture(): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $exercise = TtxExercise::create(['tenant_id' => $tenant->id, 'title' => 'Latihan', 'scenario' => 'SECRET SCENARIO']);
    $exercise = attachTtxTestPlaybook($exercise);
    TtxInject::create(['tenant_id' => $tenant->id, 'exercise_id' => $exercise->id, 'order' => 1, 'title' => 'SECRET INJECT']);
    $service = app(TtxSessionService::class);
    $session = $service->create($admin, $exercise, 'Persiapan');
    $team = $service->createTeam($admin, $session, 'Security / SOC');
    $service->updateTeamResponsibilities($admin, $session, $team->id, 'Validasi alert dan koordinasikan respons insiden.');

    return compact('tenant', 'admin', 'session', 'service', 'team');
}

test('p2 assigns an active learner to exactly one session team', function () {
    extract(preparationP2Fixture());
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($admin)->postJson(route('tenant.ttx.sessions.participants.store', $session), [
        'user_id' => $user->id, 'team_id' => $team->id, 'tenant_id' => Tenant::factory()->create()->id,
    ])->assertOk()->assertJsonPath('participants.0.id', $user->id)->assertJsonPath('participants.0.team_id', $team->id);
    expect($session->participants()->sole()->tenant_id)->toBe($tenant->id);
});

test('p2 rejects inactive foreign admin duplicate and invalid assignments', function () {
    extract(preparationP2Fixture());
    $url = route('tenant.ttx.sessions.participants.store', $session);
    $inactive = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);
    $foreign = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    foreach ([$inactive, $foreign, $admin] as $invalid) {
        $this->actingAs($admin)->postJson($url, ['user_id' => $invalid->id, 'team_id' => $team->id])->assertForbidden();
    }
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->postJson($url, ['user_id' => $user->id, 'team_id' => $team->id])->assertOk();
    $this->postJson($url, ['user_id' => $user->id, 'team_id' => $team->id])->assertConflict();
});

test('p2 learner inactive admin and other admin cannot mutate roster', function (string $actorType) {
    extract(preparationP2Fixture());
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $assignment = $service->assignParticipant($admin, $session, $user, $team);
    $actor = match ($actorType) {
        'learner' => $user,
        'inactive' => tap($admin)->update(['is_active' => false]),
        'other_admin' => User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]),
    };
    $this->actingAs($actor->fresh())->deleteJson(route('tenant.ttx.sessions.participants.destroy', [$session, $assignment->id]))->assertForbidden();
    expect($assignment->fresh())->not->toBeNull();
})->with(['learner', 'inactive', 'other_admin']);

test('p2 draft removal audits identifiers and permits reassignment', function () {
    extract(preparationP2Fixture());
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $assignment = $service->assignParticipant($admin, $session, $user, $team);
    $this->actingAs($admin)->deleteJson(route('tenant.ttx.sessions.participants.destroy', [$session, $assignment->id]))->assertOk();
    expect($assignment->fresh())->toBeNull();
    expect(AuditLog::where('action', 'ttx.session_participant_removed')->sole()->properties)
        ->toEqual(['session_id' => $session->id, 'user_id' => $user->id, 'team_id' => $team->id]);
    $service->assignParticipant($admin, $session, $user, $team);
});

test('p2 ready roster and teams are immutable', function () {
    extract(preparationP2Fixture());
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $assignment = $service->assignParticipant($admin, $session, $user, $team);
    $service->markReady($admin, $session);
    $this->actingAs($admin)->deleteJson(route('tenant.ttx.sessions.participants.destroy', [$session, $assignment->id]))->assertConflict();
    $this->postJson(route('tenant.ttx.sessions.teams.store', $session), ['name' => 'Late'])->assertForbidden();
    expect($session->fresh()->status)->toBe(TtxSessionStatus::Ready);
});

test('p2 immutable lifecycle rejects assignment and removal including stale service models', function (string $status) {
    extract(preparationP2Fixture());
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $assignment = $service->assignParticipant($admin, $session, $user, $team);
    $session->update(['status' => $status]);
    $other = User::factory()->create(['tenant_id' => $tenant->id]);
    expect(fn () => $service->assignParticipant($admin, $session, $other, $team))->toThrow(HttpException::class)
        ->and(fn () => $service->removeParticipant($admin, $session, $assignment->id))->toThrow(HttpException::class);
})->with(['in_progress', 'debrief', 'completed']);

test('p2 team and participant removal are scoped to the URL session', function () {
    extract(preparationP2Fixture());
    $otherSession = $service->create($admin, $session->exercise, 'Lain');
    $otherTeam = $service->createTeam($admin, $otherSession, 'Legal');
    $otherUser = User::factory()->create(['tenant_id' => $tenant->id]);
    $assignment = $service->assignParticipant($admin, $otherSession, $otherUser, $otherTeam);
    $this->actingAs($admin)->deleteJson(route('tenant.ttx.sessions.participants.destroy', [$session, $assignment->id]))->assertNotFound();
    $this->deleteJson(route('tenant.ttx.sessions.teams.destroy', [$session, $otherTeam->id]))->assertNotFound();
});

test('p2 preparation exposes active unassigned learners and custom teams only', function () {
    extract(preparationP2Fixture());
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);
    User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $payload = $service->preparationReadModel($admin, $session);
    expect($payload['assignable_users'])->toBe([['id' => $user->id, 'name' => $user->name]])
        ->and($payload['teams'][0]['name'])->toBe('Security / SOC')
        ->and($payload)->not->toHaveKeys(['role_options', 'can_assign_facilitator', 'facilitator_count'])
        ->and($payload['exercise_context']['scenario'])->toBe('SECRET SCENARIO')
        ->and(json_encode($payload))->not->toContain('SECRET INJECT');
});

test('p2 readiness requires a valid team membership', function () {
    extract(preparationP2Fixture());
    $payload = $service->preparationReadModel($admin, $session);
    expect($payload['readiness']['has_teams'])->toBeTrue()
        ->and($payload['readiness']['has_participants'])->toBeFalse()
        ->and($payload['readiness']['participants_have_valid_team'])->toBeFalse();
});

test('p2 cross tenant team and learner IDs are rejected by HTTP boundary', function () {
    extract(preparationP2Fixture());
    $foreign = preparationP2Fixture();
    $foreignLearner = User::factory()->create(['tenant_id' => $foreign['tenant']->id]);
    $this->actingAs($admin)->postJson(route('tenant.ttx.sessions.participants.store', $session), [
        'user_id' => $foreignLearner->id,
        'team_id' => $foreign['team']->id,
    ])->assertForbidden();
});
