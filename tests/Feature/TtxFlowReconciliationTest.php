<?php

use App\Enums\TtxSessionInjectStatus;
use App\Enums\TtxSessionStatus;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\User;
use App\Services\TtxSessionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\HttpException;

function reconciliationFixture(int $injects = 2): array
{
    $tenant = Tenant::factory()->create();
    $creator = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $otherAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $exercise = TtxExercise::create([
        'tenant_id' => $tenant->id,
        'title' => 'Skenario Ransomware',
        'scenario' => 'Gangguan layanan kritis',
        'objectives' => 'Menguji koordinasi lintas fungsi',
    ]);
    $exercise = attachTtxTestPlaybook($exercise);
    foreach (range(1, $injects) as $order) {
        TtxInject::create([
            'tenant_id' => $tenant->id,
            'exercise_id' => $exercise->id,
            'order' => $order,
            'title' => "Inject {$order}",
            'description' => "Situasi {$order}",
        ]);
    }
    $service = app(TtxSessionService::class);
    $session = $service->create($creator, $exercise, 'Session Rekonsiliasi');
    $team = $service->createTeam($creator, $session, 'Security / SOC', 'Tim respons utama');
    $service->updateTeamResponsibilities($creator, $session, $team->id, 'Validasi alert dan koordinasikan respons insiden.');

    return compact('tenant', 'creator', 'otherAdmin', 'learner', 'exercise', 'service', 'session', 'team');
}

test('creator tenant admin is the authoritative facilitator and is not a participant', function () {
    $fixture = reconciliationFixture();
    $session = $fixture['session'];

    expect($session->created_by)->toBe($fixture['creator']->id)
        ->and($session->participants()->where('user_id', $fixture['creator']->id)->exists())->toBeFalse()
        ->and($session->facilitator->id)->toBe($fixture['creator']->id);

    $payload = $fixture['service']->preparationReadModel($fixture['creator'], $session);
    expect($payload['facilitator'])->toBe(['id' => $fixture['creator']->id, 'name' => $fixture['creator']->name])
        ->and($payload['can_open_console'])->toBeTrue();
});

test('learner and another tenant admin cannot facilitate or hijack runtime actions', function () {
    $fixture = reconciliationFixture(1);
    $fixture['service']->assignParticipant($fixture['creator'], $fixture['session'], $fixture['learner'], $fixture['team']);
    $fixture['service']->markReady($fixture['creator'], $fixture['session']);

    foreach ([$fixture['learner'], $fixture['otherAdmin']] as $actor) {
        expect(fn () => $fixture['service']->start($actor, $fixture['session']->fresh()))
            ->toThrow(HttpException::class);
        $this->actingAs($actor)->get(route('tenant.ttx.sessions.console', $fixture['session']))->assertForbidden();
    }
    expect(fn () => $fixture['service']->createTeam($fixture['otherAdmin'], $fixture['session'], 'Hijack'))
        ->toThrow(HttpException::class);

    expect($fixture['service']->start($fixture['creator'], $fixture['session']->fresh())->status)
        ->toBe(TtxSessionStatus::InProgress);
});

test('only active same tenant learners can be assigned to one session team', function () {
    $fixture = reconciliationFixture();
    $assignment = $fixture['service']->assignParticipant($fixture['creator'], $fixture['session'], $fixture['learner'], $fixture['team']);
    expect($assignment->team_id)->toBe($fixture['team']->id);

    expect(fn () => $fixture['service']->assignParticipant($fixture['creator'], $fixture['session'], $fixture['learner'], $fixture['team']))
        ->toThrow(HttpException::class);

    $inactive = User::factory()->create(['tenant_id' => $fixture['tenant']->id, 'is_active' => false]);
    $foreign = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    foreach ([$inactive, $foreign, $fixture['otherAdmin']] as $invalid) {
        expect(fn () => $fixture['service']->assignParticipant($fixture['creator'], $fixture['session'], $invalid, $fixture['team']))
            ->toThrow(HttpException::class);
    }
});

test('cross session team assignment and team IDOR are rejected', function () {
    $fixture = reconciliationFixture();
    $otherSession = $fixture['service']->create($fixture['creator'], $fixture['exercise'], 'Session Lain');
    $otherTeam = $fixture['service']->createTeam($fixture['creator'], $otherSession, 'Legal');

    expect(fn () => $fixture['service']->assignParticipant($fixture['creator'], $fixture['session'], $fixture['learner'], $otherTeam))
        ->toThrow(ModelNotFoundException::class);

    $this->actingAs($fixture['creator'])
        ->postJson(route('tenant.ttx.sessions.participants.store', $fixture['session']), [
            'user_id' => $fixture['learner']->id,
            'team_id' => $otherTeam->id,
        ])->assertNotFound();
});

test('database composite integrity rejects a team from another session', function () {
    $fixture = reconciliationFixture();
    $otherSession = $fixture['service']->create($fixture['creator'], $fixture['exercise'], 'Session Lain');
    $otherTeam = $fixture['service']->createTeam($fixture['creator'], $otherSession, 'Finance');

    expect(fn () => $fixture['session']->participants()->forceCreate([
        'tenant_id' => $fixture['tenant']->id,
        'user_id' => $fixture['learner']->id,
        'team_id' => $otherTeam->id,
        'session_role' => null,
    ]))->toThrow(QueryException::class);
});

test('advance releases exactly one inject and the final inject enters debrief', function () {
    $fixture = reconciliationFixture(2);
    $assignment = $fixture['service']->assignParticipant($fixture['creator'], $fixture['session'], $fixture['learner'], $fixture['team']);
    $session = $fixture['service']->markReady($fixture['creator'], $fixture['session']);
    $fixture['service']->start($fixture['creator'], $session);
    $session = $session->fresh();

    $first = $session->injects()->where('status', TtxSessionInjectStatus::Active)->sole();
    $fixture['service']->storeResponse($fixture['learner'], $session, $first->id, [
        'decision' => 'Isolasi layanan',
        'rationale' => 'Mengurangi risiko sambil mempertahankan layanan kritis.',
        'immediate_actions' => 'Isolasi akses terdampak dan validasi log.',
        'coordination_handoff' => 'Security menyerahkan validasi layanan kepada Operations.',
    ]);
    $next = $fixture['service']->advanceInject($session, $fixture['creator']);
    expect($next->order)->toBe(2)
        ->and($session->fresh()->status)->toBe(TtxSessionStatus::InProgress)
        ->and($session->injects()->where('status', TtxSessionInjectStatus::Active)->count())->toBe(1);

    $fixture['service']->storeResponse($fixture['learner'], $session, $next->id, [
        'decision' => 'Aktifkan pemulihan',
        'rationale' => 'Containment telah diverifikasi dan kriteria pemulihan terpenuhi.',
        'immediate_actions' => 'Pulihkan akses bertahap dan aktifkan pemantauan tambahan.',
        'coordination_handoff' => 'Operations melaporkan hasil pemulihan kepada incident owner.',
    ]);
    $fixture['service']->advanceInject($session, $fixture['creator']);
    expect($session->fresh()->status)->toBe(TtxSessionStatus::Debrief)
        ->and($session->fresh()->completed_at)->toBeNull();
});

test('future injects are absent from participant workspace', function () {
    $fixture = reconciliationFixture(2);
    $fixture['service']->assignParticipant($fixture['creator'], $fixture['session'], $fixture['learner'], $fixture['team']);
    $session = $fixture['service']->markReady($fixture['creator'], $fixture['session']);
    $fixture['service']->start($fixture['creator'], $session);

    $payload = $fixture['service']->readModel($fixture['learner'], $session->fresh());
    expect($payload['injects'])->toHaveCount(1)
        ->and($payload['injects'][0]['status'])->toBe('active')
        ->and(json_encode($payload))->not->toContain('Inject 2');
});

test('ready and completed sessions are immutable for roster and response changes', function () {
    $fixture = reconciliationFixture(1);
    $assignment = $fixture['service']->assignParticipant($fixture['creator'], $fixture['session'], $fixture['learner'], $fixture['team']);
    $fixture['service']->markReady($fixture['creator'], $fixture['session']);

    expect(fn () => $fixture['service']->removeParticipant($fixture['creator'], $fixture['session']->fresh(), $assignment->id))
        ->toThrow(HttpException::class);

    $fixture['session']->update(['status' => TtxSessionStatus::Completed, 'completed_at' => now()]);
    expect(fn () => $fixture['service']->createTeam($fixture['creator'], $fixture['session']->fresh(), 'Late Team'))
        ->toThrow(HttpException::class);
});
