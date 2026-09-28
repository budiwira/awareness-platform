<?php

use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxPlaybook;
use App\Models\User;
use App\Services\TenantEntitlement;
use App\Services\TtxSessionService;
use Illuminate\Validation\ValidationException;

function ttxPlaybookPreparationFixture(): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $otherAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $participant = User::factory()->create(['tenant_id' => $tenant->id]);
    $playbook = TtxPlaybook::create([
        'tenant_id' => $tenant->id,
        'title' => 'Credential Compromise Response Playbook',
        'description' => 'Panduan respons organisasi.',
        'content' => "1. Detection & Validation\n2. Escalation & Ownership\n3. Recovery",
        'is_active' => true,
    ]);
    $exercise = TtxExercise::create([
        'tenant_id' => $tenant->id,
        'title' => 'Credential Compromise Scenario',
        'scenario' => 'Aktivitas akun mencurigakan.',
        'playbook_id' => $playbook->id,
    ]);
    TtxInject::create([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'order' => 1,
        'title' => 'Suspicious Account Activity',
        'description' => 'Situasi awal.',
    ]);
    $service = app(TtxSessionService::class);
    $session = $service->create($admin, $exercise, 'Session Playbook');
    $team = $service->createTeam($admin, $session, 'Security / SOC');

    return compact('tenant', 'admin', 'otherAdmin', 'participant', 'playbook', 'exercise', 'service', 'session', 'team');
}

test('session snapshots an authorized tenant playbook and rejects cross tenant selection', function () {
    extract(ttxPlaybookPreparationFixture());
    $foreignTenant = Tenant::factory()->create();
    $foreignPlaybook = TtxPlaybook::create([
        'tenant_id' => $foreignTenant->id,
        'title' => 'Foreign Playbook',
        'content' => 'Foreign content',
        'is_active' => true,
    ]);

    expect($session->playbook_id)->toBe($playbook->id)
        ->and($session->playbook_snapshot)->toMatchArray([
            'source_id' => $playbook->id,
            'title' => $playbook->title,
            'content' => $playbook->content,
        ]);

    $playbook->update(['content' => 'Konten berubah setelah session dibuat.']);
    expect($session->fresh()->playbook_snapshot['content'])->not->toBe($playbook->fresh()->content);

    $this->mock(TenantEntitlement::class)
        ->shouldReceive('hasFeature')->once()->andReturnTrue();
    app(TenantEntitlement::class)->shouldReceive('getEntitledFeatures')->andReturn(['ttx']);
    app(TenantEntitlement::class)->shouldReceive('getEntitledModuleIds')->andReturn([]);

    $this->actingAs($admin)
        ->post(route('tenant.ttx.sessions.store', $exercise), [
            'playbook_id' => $foreignPlaybook->id,
            'title' => 'Session ilegal',
        ])
        ->assertSessionHasErrors('playbook_id');
});

test('only creator tenant admin can update draft team responsibilities with server derived ownership', function () {
    extract(ttxPlaybookPreparationFixture());
    $foreignTenant = Tenant::factory()->create();

    $this->actingAs($admin)->putJson(
        route('tenant.ttx.sessions.teams.responsibilities.update', [$session, $team]),
        [
            'responsibilities' => 'Validasi alert, investigasi autentikasi, dan tentukan cakupan insiden.',
            'tenant_id' => $foreignTenant->id,
            'session_id' => 999999,
            'id' => 999999,
        ]
    )->assertOk()->assertJsonPath('teams.0.responsibilities', 'Validasi alert, investigasi autentikasi, dan tentukan cakupan insiden.');

    expect($team->fresh()->tenant_id)->toBe($tenant->id)
        ->and($team->fresh()->session_id)->toBe($session->id)
        ->and($team->fresh()->id)->toBe($team->id)
        ->and($team->isFillable('tenant_id'))->toBeFalse()
        ->and($team->isFillable('session_id'))->toBeFalse();

    $this->actingAs($participant)
        ->putJson(route('tenant.ttx.sessions.teams.responsibilities.update', [$session, $team]), ['responsibilities' => 'Mutasi peserta tidak sah.'])
        ->assertForbidden();
    $this->actingAs($otherAdmin)
        ->putJson(route('tenant.ttx.sessions.teams.responsibilities.update', [$session, $team]), ['responsibilities' => 'Mutasi admin lain tidak sah.'])
        ->assertForbidden();
});

test('cross session team responsibility IDOR is rejected', function () {
    extract(ttxPlaybookPreparationFixture());
    $otherSession = $service->create($admin, $exercise, 'Session lain');
    $otherTeam = $service->createTeam($admin, $otherSession, 'Operations');

    $this->actingAs($admin)
        ->putJson(route('tenant.ttx.sessions.teams.responsibilities.update', [$session, $otherTeam]), [
            'responsibilities' => 'Contain accounts and revoke active sessions.',
        ])
        ->assertNotFound();
});

test('readiness requires meaningful preparation and responsibilities become immutable after ready', function () {
    extract(ttxPlaybookPreparationFixture());
    $service->assignParticipant($admin, $session, $participant, $team);

    expect(fn () => $service->markReady($admin, $session))->toThrow(ValidationException::class);

    $service->updateTeamResponsibilities($admin, $session, $team->id, 'Validasi alert, investigasi autentikasi, dan tentukan cakupan insiden.');
    $service->markReady($admin, $session->fresh());

    $this->actingAs($admin)
        ->putJson(route('tenant.ttx.sessions.teams.responsibilities.update', [$session, $team]), [
            'responsibilities' => 'Perubahan setelah READY.',
        ])
        ->assertForbidden();
});

test('facilitator receives playbook and all preparation while participant receives only own team safe data', function () {
    extract(ttxPlaybookPreparationFixture());
    $service->updateTeamResponsibilities($admin, $session, $team->id, 'Validasi alert dan tentukan cakupan insiden.');
    $service->assignParticipant($admin, $session, $participant, $team);
    $service->markReady($admin, $session);
    $service->start($admin, $session->fresh());

    $facilitator = $service->readModel($admin, $session->fresh());
    $learner = $service->readModel($participant, $session->fresh());

    expect($facilitator['playbook']['title'])->toBe($playbook->title)
        ->and($facilitator['playbook']['content'])->toContain('Detection & Validation')
        ->and($facilitator['teams'][0]['responsibilities'])->toContain('Validasi alert')
        ->and($learner['playbook']['title'])->toBe($playbook->title)
        ->and($learner['playbook']['legacy_reference'])->toBeTrue()
        ->and($learner['playbook'])->not->toHaveKey('content')
        ->and($learner['participant_team']['id'])->toBe($team->id)
        ->and($learner['participant_team']['responsibilities'])->toContain('Validasi alert')
        ->and($learner['teams'])->toHaveCount(1)
        ->and(json_encode($learner))->not->toContain('Escalation & Ownership');
});
