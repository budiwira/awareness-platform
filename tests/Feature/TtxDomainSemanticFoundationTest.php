<?php

use App\Domain\Tabletop\ExerciseCapabilityCatalog;
use App\Domain\Tabletop\PlaybookPhaseStructure;
use App\Enums\TtxSessionStatus;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TtxActionItem;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxPlaybook;
use App\Models\TtxSessionEvaluation;
use App\Models\User;
use App\Services\TtxSessionService;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

function ttxSemanticPhases(): array
{
    return PlaybookPhaseStructure::normalize([
        [
            'key' => 'detection_validation',
            'title' => 'Detection & Validation',
            'guidance' => 'Validasi indikasi dan pertahankan bukti awal.',
            'participant_summary' => 'Validasi indikasi menggunakan fakta yang tersedia.',
            'capability_codes' => ['EX-1'],
        ],
        [
            'key' => 'escalation_ownership',
            'title' => 'Escalation & Ownership',
            'guidance' => 'Tetapkan pemilik, jalur eskalasi, dan otoritas keputusan.',
            'participant_summary' => 'Tetapkan kepemilikan dan jalur eskalasi yang jelas.',
            'capability_codes' => ['EX-2'],
        ],
        [
            'key' => 'recovery',
            'title' => 'Recovery',
            'guidance' => 'Pulihkan operasi dengan kriteria dan pemantauan yang jelas.',
            'participant_summary' => 'Pulihkan layanan secara terkendali.',
            'capability_codes' => ['EX-6'],
        ],
    ]);
}

function ttxSemanticFixture(?array $capabilityCodes = null): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $otherAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $participant = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Editor Satu']);
    $teammate = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Editor Dua']);
    $otherTeamUser = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Tim Lain']);

    $playbook = TtxPlaybook::create([
        'tenant_id' => $tenant->id,
        'title' => 'Structured Playbook',
        'content' => 'Legacy text remains available.',
        'is_active' => true,
    ]);
    $playbook->forceFill(['structured_phases' => ttxSemanticPhases()])->save();

    $exercise = TtxExercise::create([
        'tenant_id' => $tenant->id,
        'title' => 'Structured Scenario',
        'scenario' => 'A credential compromise is being exercised.',
        'playbook_id' => $playbook->id,
    ]);
    $exercise->forceFill([
        'capability_codes' => ExerciseCapabilityCatalog::normalize($capabilityCodes ?? ['EX-1', 'EX-2', 'EX-6']),
    ])->save();

    $inject = TtxInject::create([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'order' => 1,
        'title' => 'Suspicious activity',
        'description' => 'Situasi awal.',
    ]);
    $inject->forceFill(['capability_codes' => ['EX-1']])->save();

    $service = app(TtxSessionService::class);
    $session = $service->create($admin, $exercise->fresh(), 'Structured session');
    $securityTeam = $service->createTeam($admin, $session, 'Security');
    $operationsTeam = $service->createTeam($admin, $session, 'Operations');
    $service->assignParticipant($admin, $session, $participant, $securityTeam);
    $service->assignParticipant($admin, $session, $teammate, $securityTeam);
    $service->assignParticipant($admin, $session, $otherTeamUser, $operationsTeam);

    return compact(
        'tenant', 'admin', 'otherAdmin', 'participant', 'teammate', 'otherTeamUser',
        'playbook', 'exercise', 'inject', 'service', 'session', 'securityTeam', 'operationsTeam'
    );
}

function assignSemanticPrimaryOwners(array $fixture): void
{
    foreach (PlaybookPhaseStructure::relevantKeys($fixture['session']->playbook_snapshot, $fixture['session']->exercise_snapshot) as $phaseKey) {
        $fixture['service']->assignResponsibility(
            $fixture['admin'],
            $fixture['session'],
            $fixture['securityTeam']->id,
            $phaseKey,
            'primary'
        );
    }
}

function validV2ResponsePayload(int $sessionInjectId): array
{
    return [
        'session_inject_id' => $sessionInjectId,
        'decision' => 'Revoke the suspicious session and protect the business process.',
        'rationale' => 'Confirmed abuse creates a material risk to finance operations.',
        'immediate_actions' => 'Security revokes sessions; Operations validates restored access.',
        'coordination_handoff' => 'Security hands recovery validation to Operations and briefs Management.',
    ];
}

function finalCDebriefFixture(): array
{
    $fixture = ttxSemanticFixture(['EX-1', 'EX-2']);
    assignSemanticPrimaryOwners($fixture);
    $fixture['service']->assignResponsibility($fixture['admin'], $fixture['session'], $fixture['operationsTeam']->id, 'escalation_ownership', 'support');
    $fixture['service']->markReady($fixture['admin'], $fixture['session']->fresh());
    $fixture['service']->start($fixture['admin'], $fixture['session']->fresh());
    $inject = $fixture['session']->injects()->first();
    $fixture['service']->storeResponse($fixture['participant'], $fixture['session']->fresh(), $inject->id, validV2ResponsePayload($inject->id));
    $fixture['service']->advanceInject($fixture['session']->fresh(), $fixture['admin'], true);
    $fixture['session'] = $fixture['session']->fresh();

    return $fixture;
}

function finalCEvaluations(): array
{
    return collect(['detection_triage', 'escalation_ownership'])->map(fn ($dimension) => [
        'dimension' => $dimension, 'rating' => 'effective',
        'evidence' => 'Observed team handoff.', 'finding' => 'Ownership enabled effective coordination.',
    ])->all();
}

function finalCAar(): array
{
    return ['overall_summary' => 'Safe final summary.', 'strengths' => 'Clear ownership.', 'improvement_areas' => 'Recovery criteria.', 'key_lessons' => 'Define handoffs.'];
}

test('FINAL-C HTTP evaluation saves finding and older partial clients cannot erase it', function () {
    $fixture = finalCDebriefFixture();
    $url = route('tenant.ttx.sessions.evaluation.update', $fixture['session']);
    $this->actingAs($fixture['admin'])->putJson($url, ['evaluations' => finalCEvaluations(), 'tenant_id' => 'spoof', 'session_id' => 999, 'updated_by' => $fixture['participant']->id])->assertOk();
    $this->putJson($url, ['evaluations' => [['dimension' => 'detection_triage', 'rating' => 'strong', 'evidence' => 'Updated observable evidence.', 'tenant_id' => 'spoof', 'updated_by' => $fixture['participant']->id]]])
        ->assertOk()->assertJsonPath('dimensions.0.finding', finalCEvaluations()[0]['finding']);
    $stored = $fixture['session']->evaluations()->where('dimension', 'detection_triage')->first();
    expect($stored->finding)->toBe(finalCEvaluations()[0]['finding'])
        ->and($stored->tenant_id)->toBe($fixture['tenant']->id)
        ->and($stored->updated_by)->toBe($fixture['admin']->id);
    $this->putJson($url, ['evaluations' => [['dimension' => 'detection_triage', 'rating' => 'strong', 'finding' => null]]])->assertOk();
    expect($stored->fresh()->finding)->toBeNull();
});

test('FINAL-C debrief projects snapshot subset structured ownership handoff and no response without hiding historical evaluation', function () {
    $fixture = finalCDebriefFixture();
    $fixture['exercise']->forceFill(['capability_codes' => ['EX-6']])->save();
    $fixture['playbook']->forceFill(['structured_phases' => [ttxSemanticPhases()[2]]])->save();
    $fixture['service']->updateEvaluation($fixture['admin'], $fixture['session'], [['dimension' => 'recovery_improvement', 'rating' => 'developing', 'finding' => 'Historical finding.']]);
    $payload = $this->actingAs($fixture['admin'])->getJson(route('tenant.ttx.sessions.debrief', $fixture['session']))->assertOk()->json();
    expect(collect($payload['dimensions'])->where('required', true)->pluck('code')->all())->toBe(['EX-1', 'EX-2'])
        ->and($payload['dimensions'][5]['finding'])->toBe('Historical finding.')
        ->and($payload['dimensions'][5]['has_evaluation'])->toBeTrue()
        ->and($payload['session']['playbook']['structured_phases'])->toHaveCount(3)
        ->and(collect($payload['session']['teams'])->flatMap(fn ($team) => $team['responsibility_assignments'])->pluck('role')->all())->toContain('primary', 'support');
    $responses = collect($payload['session']['injects'][0]['team_responses']);
    expect($responses->firstWhere('team.id', $fixture['securityTeam']->id)['response']['coordination_handoff'])->toContain('Operations')
        ->and($responses->firstWhere('team.id', $fixture['operationsTeam']->id)['response'])->toBeNull();
});

test('FINAL-C semantic action HTTP writes use snapshot membership and server ownership', function () {
    $fixture = finalCDebriefFixture();
    $base = ['title' => 'Clarify handoff', 'owner' => 'SOC', 'priority' => 'high', 'status' => 'open', 'category' => 'playbook_improvement', 'capability_code' => 'EX-2', 'playbook_phase_key' => 'escalation_ownership'];
    $fixture['playbook']->forceFill(['structured_phases' => [ttxSemanticPhases()[2]]])->save();
    $fixture['exercise']->forceFill(['capability_codes' => ['EX-6']])->save();
    $id = $this->actingAs($fixture['admin'])->postJson(route('tenant.ttx.sessions.action-items.store', $fixture['session']), [...$base, 'tenant_id' => 'spoof', 'session_id' => 999, 'created_by' => $fixture['participant']->id])->assertCreated()->json('id');
    $item = TtxActionItem::findOrFail($id);
    expect($item->tenant_id)->toBe($fixture['tenant']->id)->and($item->session_id)->toBe($fixture['session']->id)->and($item->created_by)->toBe($fixture['admin']->id);
    $url = route('tenant.ttx.sessions.action-items.update', [$fixture['session'], $item]);
    $this->putJson($url, [...$base, 'category' => 'corrective_action', 'updated_by' => $fixture['participant']->id])->assertOk()->assertJsonPath('category', 'corrective_action');
    $this->putJson($url, [...$base, 'capability_code' => 'EX-6'])->assertUnprocessable()->assertJsonValidationErrors('capability_code');
    $this->postJson(route('tenant.ttx.sessions.action-items.store', $fixture['session']), [...$base, 'capability_code' => 'EX-6'])->assertUnprocessable();
    $this->putJson($url, [...$base, 'playbook_phase_key' => 'arbitrary'])->assertUnprocessable();
    $this->putJson($url, [...$base, 'category' => 'score'])->assertUnprocessable();
    $this->putJson($url, [...$base, 'category' => null, 'capability_code' => null, 'playbook_phase_key' => null])->assertOk();
    expect($item->fresh()->updated_by)->toBe($fixture['admin']->id);
});

test('FINAL-C finalizes V2 through HTTP and participant result never receives facilitator semantics', function () {
    $fixture = finalCDebriefFixture();
    $this->actingAs($fixture['admin'])->putJson(route('tenant.ttx.sessions.evaluation.update', $fixture['session']), ['evaluations' => finalCEvaluations()])->assertOk();
    $this->putJson(route('tenant.ttx.sessions.aar.update', $fixture['session']), [...finalCAar(), 'tenant_id' => 'spoof', 'session_id' => 999, 'updated_by' => $fixture['participant']->id])->assertOk();
    expect($fixture['session']->afterActionSummary()->first()->updated_by)->toBe($fixture['admin']->id);
    $this->postJson(route('tenant.ttx.sessions.action-items.store', $fixture['session']), ['title' => 'Improve playbook', 'owner' => 'SOC', 'priority' => 'medium', 'status' => 'open', 'category' => 'playbook_improvement', 'capability_code' => 'EX-2'])->assertCreated();
    $this->postJson(route('tenant.ttx.sessions.complete', $fixture['session']))->assertOk()->assertJsonPath('session.status', 'completed')->assertJsonPath('dimensions.0.finding', finalCEvaluations()[0]['finding'])->assertJsonPath('action_items.0.category', 'playbook_improvement');
    $this->get(route('tenant.ttx.sessions.result', $fixture['session']))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Tenant/Ttx/Sessions/Debrief')->where('resultMode', true));
    $payload = $this->actingAs($fixture['participant'])->getJson(route('tenant.ttx.sessions.show', $fixture['session']))->assertOk()->json();
    expect(json_encode($payload))->not->toContain(finalCEvaluations()[0]['finding'], 'action_items', 'dimensions', 'guidance')
        ->and($payload['teams'])->toHaveCount(1)->and($payload['outcome'])->toBe(finalCAar());
});

test('FINAL-C incomplete V2 evidence or finding blocks finalization', function (string $field) {
    $fixture = finalCDebriefFixture();
    $entries = finalCEvaluations();
    $entries[0][$field] = '   ';
    $this->actingAs($fixture['admin'])->putJson(route('tenant.ttx.sessions.evaluation.update', $fixture['session']), ['evaluations' => $entries])->assertOk();
    $this->putJson(route('tenant.ttx.sessions.aar.update', $fixture['session']), finalCAar())->assertOk();
    $this->postJson(route('tenant.ttx.sessions.complete', $fixture['session']))->assertUnprocessable()->assertJsonValidationErrors('evaluation');
    expect($fixture['session']->fresh()->status)->toBe(TtxSessionStatus::Debrief);
})->with(['evidence', 'finding']);

test('FINAL-C V2 action capability cannot use evaluation fallback when snapshot membership is absent', function (?array $codes) {
    $fixture = finalCDebriefFixture();
    $fixture['session']->forceFill(['exercise_snapshot' => [...$fixture['session']->exercise_snapshot, 'capability_codes' => $codes]])->save();
    $base = ['title' => 'Optional action', 'owner' => 'SOC', 'priority' => 'medium', 'status' => 'open'];
    $this->actingAs($fixture['admin'])->postJson(route('tenant.ttx.sessions.action-items.store', $fixture['session']), [...$base, 'capability_code' => 'EX-1'])->assertUnprocessable()->assertJsonValidationErrors('capability_code');
    $this->postJson(route('tenant.ttx.sessions.action-items.store', $fixture['session']), [...$base, 'capability_code' => null])->assertCreated();
})->with(['missing' => [null], 'empty' => [[]]]);

test('FINAL-C updated legacy action must clear non-session capability but historical read remains intact', function () {
    $fixture = finalCDebriefFixture();
    $base = ['title' => 'Historical action', 'owner' => 'SOC', 'priority' => 'medium', 'status' => 'open'];
    $item = TtxActionItem::forceCreate(['tenant_id' => $fixture['tenant']->id, 'session_id' => $fixture['session']->id, ...$base, 'capability_code' => 'EX-6', 'created_by' => $fixture['admin']->id, 'updated_by' => $fixture['admin']->id]);
    $this->actingAs($fixture['admin'])->getJson(route('tenant.ttx.sessions.debrief', $fixture['session']))->assertOk()->assertJsonPath('action_items.0.capability_code', 'EX-6');
    $url = route('tenant.ttx.sessions.action-items.update', [$fixture['session'], $item]);
    $this->putJson($url, $base)->assertUnprocessable()->assertJsonValidationErrors('capability_code');
    expect($item->fresh()->capability_code)->toBe('EX-6');
    $this->putJson($url, [...$base, 'capability_code' => null])->assertOk()->assertJsonPath('capability_code', null);
});

test('canonical capabilities reject unknown codes and structured phases reject arbitrary fields', function () {
    expect(ExerciseCapabilityCatalog::codes())->toBe(['EX-1', 'EX-2', 'EX-3', 'EX-4', 'EX-5', 'EX-6'])
        ->and(fn () => ExerciseCapabilityCatalog::normalize(['EX-7']))->toThrow(ValidationException::class)
        ->and(fn () => PlaybookPhaseStructure::normalize([[
            ...ttxSemanticPhases()[0],
            'answer_key' => 'Do not accept arbitrary JSON.',
        ]]))->toThrow(ValidationException::class);
});

test('session creation refuses unknown persisted capability references', function () {
    $fixture = ttxSemanticFixture();
    $fixture['exercise']->forceFill(['capability_codes' => ['EX-9']])->save();

    expect(fn () => $fixture['service']->create(
        $fixture['admin'], $fixture['exercise']->fresh(), 'Invalid capability session'
    ))->toThrow(ValidationException::class);
});

test('structured capability and playbook snapshots are immutable while legacy playbooks remain supported', function () {
    $fixture = ttxSemanticFixture();
    $snapshot = $fixture['session']->playbook_snapshot;

    expect($fixture['session']->response_contract_version)->toBe(2)
        ->and($snapshot['structured_phases'])->toHaveCount(3)
        ->and($fixture['session']->exercise_snapshot['capability_codes'])->toBe(['EX-1', 'EX-2', 'EX-6'])
        ->and($fixture['session']->injects()->first()->inject_snapshot['capability_codes'])->toBe(['EX-1']);

    $fixture['playbook']->forceFill(['structured_phases' => [ttxSemanticPhases()[0]]])->save();
    expect($fixture['session']->fresh()->playbook_snapshot)->toEqual($snapshot);

    $legacyPlaybook = TtxPlaybook::create([
        'tenant_id' => $fixture['tenant']->id,
        'title' => 'Legacy Playbook',
        'content' => 'Plain text only.',
        'is_active' => true,
    ]);
    $legacyExercise = TtxExercise::create([
        'tenant_id' => $fixture['tenant']->id,
        'title' => 'Legacy Scenario',
        'scenario' => 'Legacy scenario body.',
        'playbook_id' => $legacyPlaybook->id,
    ]);
    TtxInject::create([
        'tenant_id' => $fixture['tenant']->id,
        'exercise_id' => $legacyExercise->id,
        'order' => 1,
        'title' => 'Legacy Inject',
    ]);
    $legacySession = $fixture['service']->create($fixture['admin'], $legacyExercise, 'Legacy content session');
    expect($legacySession->playbook_snapshot['content'])->toBe('Plain text only.')
        ->and($legacySession->playbook_snapshot['structured_phases'])->toBeNull();
});

test('creator assigns normalized primary and support ownership with server-derived scope and audit', function () {
    $fixture = ttxSemanticFixture();
    $primary = $fixture['service']->assignResponsibility(
        $fixture['admin'], $fixture['session'], $fixture['securityTeam']->id, 'detection_validation', 'primary'
    );
    $support = $fixture['service']->assignResponsibility(
        $fixture['admin'], $fixture['session'], $fixture['operationsTeam']->id, 'detection_validation', 'support'
    );

    expect($primary->tenant_id)->toBe($fixture['tenant']->id)
        ->and($primary->session_id)->toBe($fixture['session']->id)
        ->and($support->role)->toBe('support')
        ->and($primary->isFillable('tenant_id'))->toBeFalse()
        ->and(AuditLog::where('action', 'ttx.responsibility_assigned')->count())->toBe(2);

    $fixture['service']->updateResponsibilityAssignment($fixture['admin'], $fixture['session'], $support->id, 'support');
    $fixture['service']->removeResponsibilityAssignment($fixture['admin'], $fixture['session'], $support->id);
    expect(AuditLog::where('action', 'ttx.responsibility_changed')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'ttx.responsibility_removed')->exists())->toBeTrue();
});

test('responsibility routes reject participants other admins and cross-session team identifiers', function () {
    $fixture = ttxSemanticFixture();
    $payload = [
        'team_id' => $fixture['securityTeam']->id,
        'playbook_phase_key' => 'detection_validation',
        'role' => 'primary',
        'tenant_id' => '00000000-0000-0000-0000-000000000000',
        'session_id' => 999999,
    ];

    $this->actingAs($fixture['participant'])
        ->postJson(route('tenant.ttx.sessions.responsibility-assignments.store', $fixture['session']), $payload)
        ->assertForbidden();
    $this->actingAs($fixture['otherAdmin'])
        ->postJson(route('tenant.ttx.sessions.responsibility-assignments.store', $fixture['session']), $payload)
        ->assertForbidden();
    $foreignTenant = Tenant::factory()->create();
    $foreignAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $foreignTenant->id]);
    $this->actingAs($foreignAdmin)
        ->postJson(route('tenant.ttx.sessions.responsibility-assignments.store', $fixture['session']), $payload)
        ->assertForbidden();

    $otherSession = $fixture['service']->create($fixture['admin'], $fixture['exercise'], 'Other session');
    $otherTeam = $fixture['service']->createTeam($fixture['admin'], $otherSession, 'Other team');
    $payload['team_id'] = $otherTeam->id;
    $this->actingAs($fixture['admin'])
        ->postJson(route('tenant.ttx.sessions.responsibility-assignments.store', $fixture['session']), $payload)
        ->assertNotFound();
});

test('duplicate team phase and multiple primary owners are rejected while supports may be multiple', function () {
    $fixture = ttxSemanticFixture();
    $fixture['service']->assignResponsibility(
        $fixture['admin'], $fixture['session'], $fixture['securityTeam']->id, 'detection_validation', 'primary'
    );

    expect(fn () => $fixture['service']->assignResponsibility(
        $fixture['admin'], $fixture['session'], $fixture['securityTeam']->id, 'detection_validation', 'support'
    ))->toThrow(ValidationException::class);
    expect(fn () => $fixture['service']->assignResponsibility(
        $fixture['admin'], $fixture['session'], $fixture['operationsTeam']->id, 'detection_validation', 'primary'
    ))->toThrow(ValidationException::class);

    $fixture['service']->assignResponsibility(
        $fixture['admin'], $fixture['session'], $fixture['operationsTeam']->id, 'detection_validation', 'support'
    );
    expect($fixture['session']->responsibilityAssignments()->where('role', 'support')->count())->toBe(1);
});

test('V2 readiness requires only relevant primary ownership and leaves preparation notes optional', function () {
    $fixture = ttxSemanticFixture(['EX-1']);
    $relevant = PlaybookPhaseStructure::relevantKeys($fixture['session']->playbook_snapshot, $fixture['session']->exercise_snapshot);
    expect($relevant)->toBe(['detection_validation']);
    expect(fn () => $fixture['service']->markReady($fixture['admin'], $fixture['session']))
        ->toThrow(ValidationException::class);

    $fixture['service']->assignResponsibility(
        $fixture['admin'], $fixture['session'], $fixture['securityTeam']->id, 'detection_validation', 'primary'
    );
    $fixture['service']->markReady($fixture['admin'], $fixture['session']->fresh());

    expect($fixture['session']->fresh()->status)->toBe(TtxSessionStatus::Ready)
        ->and($fixture['securityTeam']->fresh()->responsibilities)->toBeNull();
    expect(fn () => $fixture['service']->assignResponsibility(
        $fixture['admin'], $fixture['session']->fresh(), $fixture['securityTeam']->id, 'recovery', 'support'
    ))->toThrow(HttpException::class);
});

test('V2 response requires four meaningful core fields and owner is not required', function () {
    $fixture = ttxSemanticFixture();
    assignSemanticPrimaryOwners($fixture);
    $fixture['service']->markReady($fixture['admin'], $fixture['session']->fresh());
    $fixture['service']->start($fixture['admin'], $fixture['session']->fresh());
    $sessionInject = $fixture['session']->injects()->first();
    $valid = validV2ResponsePayload($sessionInject->id);

    foreach (['decision', 'rationale', 'immediate_actions', 'coordination_handoff'] as $field) {
        $invalid = $valid;
        $invalid[$field] = '   ';
        $this->actingAs($fixture['participant'])
            ->postJson(route('tenant.ttx.sessions.responses.store', $fixture['session']), $invalid)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    $this->actingAs($fixture['participant'])
        ->postJson(route('tenant.ttx.sessions.responses.store', $fixture['session']), $valid)
        ->assertCreated()
        ->assertJsonPath('coordination_handoff', $valid['coordination_handoff']);
    $this->assertDatabaseHas('ttx_session_responses', [
        'session_id' => $fixture['session']->id,
        'owner' => null,
        'coordination_handoff' => $valid['coordination_handoff'],
    ]);
});

test('historical V1 response keeps decision-only validation and remains readable', function () {
    $fixture = ttxSemanticFixture();
    $fixture['session']->forceFill([
        'response_contract_version' => 1,
        'status' => TtxSessionStatus::InProgress,
        'started_at' => now(),
    ])->save();
    $sessionInject = $fixture['session']->injects()->first();
    $sessionInject->forceFill(['status' => 'active', 'released_at' => now(), 'released_by' => $fixture['admin']->id])->save();

    $fixture['service']->storeResponse(
        $fixture['participant'], $fixture['session']->fresh(), $sessionInject->id, ['decision' => 'Legacy decision only.']
    );
    $payload = $fixture['service']->readModel($fixture['participant'], $fixture['session']->fresh());
    expect($payload['response_contract_version'])->toBe(1)
        ->and($payload['injects'][0]['response']['decision'])->toBe('Legacy decision only.')
        ->and($payload['injects'][0]['response']['coordination_handoff'])->toBeNull();
});

test('safe collaborator attribution exposes only an active same-team name and no editor IDs', function () {
    $fixture = ttxSemanticFixture();
    assignSemanticPrimaryOwners($fixture);
    $fixture['service']->markReady($fixture['admin'], $fixture['session']->fresh());
    $fixture['service']->start($fixture['admin'], $fixture['session']->fresh());
    $sessionInject = $fixture['session']->injects()->first();
    $fixture['service']->storeResponse(
        $fixture['participant'], $fixture['session']->fresh(), $sessionInject->id, validV2ResponsePayload($sessionInject->id)
    );

    $teammatePayload = $fixture['service']->readModel($fixture['teammate'], $fixture['session']->fresh());
    $otherTeamPayload = $fixture['service']->readModel($fixture['otherTeamUser'], $fixture['session']->fresh());
    $safe = $teammatePayload['injects'][0]['response'];
    expect($safe['last_edited_by_name'])->toBe('Editor Satu')
        ->and($safe['last_edited_at'])->not->toBeNull()
        ->and($safe)->not->toHaveKeys(['submitted_by', 'last_edited_by'])
        ->and($otherTeamPayload['injects'][0]['response'])->toBeNull()
        ->and(json_encode($otherTeamPayload))->not->toContain('Editor Satu');

    $fixture['participant']->update(['is_active' => false]);
    $afterDeactivation = $fixture['service']->readModel($fixture['teammate'], $fixture['session']->fresh());
    expect($afterDeactivation['injects'][0]['response']['last_edited_by_name'])->toBeNull();
});

test('V2 completion requires evidence and meaningful findings for relevant capabilities', function () {
    $fixture = ttxSemanticFixture();
    $fixture['session']->forceFill([
        'status' => TtxSessionStatus::Debrief,
        'started_at' => now()->subHour(),
        'debrief_started_at' => now(),
    ])->save();

    $fixture['service']->updateEvaluation($fixture['admin'], $fixture['session']->fresh(), [
        ['dimension' => 'detection_triage', 'rating' => 'effective', 'evidence' => 'Alert tervalidasi.', 'finding' => null],
        ['dimension' => 'escalation_ownership', 'rating' => 'effective', 'evidence' => 'Owner ditetapkan.', 'finding' => 'Eskalasi efektif.'],
        ['dimension' => 'recovery_improvement', 'rating' => 'developing', 'evidence' => 'Recovery dibahas.', 'finding' => 'Kriteria perlu diperjelas.'],
    ]);
    $fixture['service']->updateAfterActionSummary($fixture['admin'], $fixture['session']->fresh(), [
        'overall_summary' => 'Exercise completed.',
        'strengths' => 'Fast triage.',
        'improvement_areas' => 'Recovery criteria.',
        'key_lessons' => 'Clarify ownership.',
    ]);

    expect(fn () => $fixture['service']->complete($fixture['admin'], $fixture['session']->fresh()))
        ->toThrow(ValidationException::class);

    $fixture['service']->updateEvaluation($fixture['admin'], $fixture['session']->fresh(), [[
        'dimension' => 'detection_triage',
        'rating' => 'effective',
        'evidence' => 'Alert tervalidasi.',
        'finding' => 'Triage berjalan dengan bukti yang cukup.',
    ]]);
    $fixture['service']->complete($fixture['admin'], $fixture['session']->fresh());
    expect($fixture['session']->fresh()->status)->toBe(TtxSessionStatus::Completed);
});

test('action items validate canonical capability phase and category while legacy null semantics remain valid', function () {
    $fixture = ttxSemanticFixture();
    $fixture['session']->forceFill(['status' => TtxSessionStatus::Debrief, 'debrief_started_at' => now()])->save();
    $base = [
        'title' => 'Clarify escalation ownership',
        'owner' => 'Incident Management',
        'priority' => 'high',
        'due_date' => null,
        'status' => 'open',
    ];
    $item = $fixture['service']->createActionItem($fixture['admin'], $fixture['session']->fresh(), [
        ...$base,
        'capability_code' => 'EX-2',
        'playbook_phase_key' => 'escalation_ownership',
        'category' => 'playbook_improvement',
    ]);
    expect($item->capability_code)->toBe('EX-2')
        ->and($item->category)->toBe('playbook_improvement');

    expect(fn () => $fixture['service']->createActionItem($fixture['admin'], $fixture['session']->fresh(), [
        ...$base, 'capability_code' => 'EX-9',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $fixture['service']->createActionItem($fixture['admin'], $fixture['session']->fresh(), [
        ...$base, 'playbook_phase_key' => 'not_in_snapshot',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $fixture['service']->createActionItem($fixture['admin'], $fixture['session']->fresh(), [
        ...$base, 'category' => 'automatic_score',
    ]))->toThrow(ValidationException::class);

    $legacy = TtxActionItem::forceCreate([
        'tenant_id' => $fixture['tenant']->id,
        'session_id' => $fixture['session']->id,
        ...$base,
        'created_by' => $fixture['admin']->id,
        'updated_by' => $fixture['admin']->id,
    ]);
    $legacyEvaluation = TtxSessionEvaluation::forceCreate([
        'tenant_id' => $fixture['tenant']->id,
        'session_id' => $fixture['session']->id,
        'dimension' => 'detection_triage',
        'rating' => 'effective',
        'updated_by' => $fixture['admin']->id,
    ]);
    expect($legacy->fresh()->category)->toBeNull()
        ->and($legacyEvaluation->fresh()->finding)->toBeNull();
});
