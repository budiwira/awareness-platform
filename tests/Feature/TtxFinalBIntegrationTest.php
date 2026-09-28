<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxPlaybook;
use App\Models\User;
use App\Services\TtxSessionService;
use Inertia\Testing\AssertableInertia as Assert;

function finalBFixture(): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $participant = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Anggota SOC']);
    $teammate = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Rekan SOC']);
    $otherUser = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Anggota Operations']);
    $phases = [
        ['key' => 'detection_validation', 'title' => 'Detection & Validation', 'guidance' => 'FACILITATOR PRIVATE GUIDANCE', 'participant_summary' => 'Validasi indikasi dengan fakta yang tersedia.', 'capability_codes' => ['EX-1']],
        ['key' => 'escalation_ownership', 'title' => 'Escalation & Ownership', 'guidance' => 'FACILITATOR ESCALATION GUIDANCE', 'participant_summary' => 'Koordinasikan kepemilikan respons organisasi.', 'capability_codes' => ['EX-2']],
        ['key' => 'recovery', 'title' => 'Recovery', 'guidance' => 'IRRELEVANT GUIDANCE', 'participant_summary' => 'IRRELEVANT SUMMARY', 'capability_codes' => ['EX-6']],
    ];
    $playbook = TtxPlaybook::create(['tenant_id' => $tenant->id, 'title' => 'Historical response baseline', 'description' => 'Panduan respons organisasi.', 'content' => 'PRIVATE LEGACY CONTENT', 'is_active' => true]);
    $playbook->forceFill(['structured_phases' => $phases])->save();
    $exercise = TtxExercise::create(['tenant_id' => $tenant->id, 'title' => 'Incident exercise', 'scenario' => 'Kompromi kredensial.', 'objectives' => 'Validasi dan eskalasi.', 'scope' => '45 menit', 'playbook_id' => $playbook->id]);
    $exercise->forceFill(['capability_codes' => ['EX-1', 'EX-2']])->save();
    TtxInject::create(['tenant_id' => $tenant->id, 'exercise_id' => $exercise->id, 'order' => 1, 'title' => 'Initial situation', 'description' => 'Aktivitas mencurigakan.']);
    TtxInject::create(['tenant_id' => $tenant->id, 'exercise_id' => $exercise->id, 'order' => 2, 'title' => 'FUTURE INJECT TITLE', 'description' => 'FUTURE INJECT NARRATIVE']);
    $service = app(TtxSessionService::class);
    $session = $service->create($admin, $exercise->fresh(), 'FINAL-B exercise');
    $security = $service->createTeam($admin, $session, 'SOC');
    $operations = $service->createTeam($admin, $session, 'Operations');
    $service->assignParticipant($admin, $session, $participant, $security);
    $service->assignParticipant($admin, $session, $teammate, $security);
    $service->assignParticipant($admin, $session, $otherUser, $operations);
    $primary = $service->assignResponsibility($admin, $session, $security->id, 'detection_validation', 'primary');
    $service->assignResponsibility($admin, $session, $operations->id, 'detection_validation', 'support');
    $service->assignResponsibility($admin, $session, $operations->id, 'escalation_ownership', 'primary');
    $service->assignResponsibility($admin, $session, $security->id, 'escalation_ownership', 'support');

    return compact('tenant', 'admin', 'participant', 'teammate', 'otherUser', 'playbook', 'exercise', 'service', 'session', 'security', 'operations', 'primary');
}

test('participant POST and PUT responses use the same safe V2 projection as GET', function () {
    $fixture = finalBFixture();
    $admin = $fixture['admin'];
    $participant = $fixture['participant'];
    $session = $fixture['service']->start($admin, $fixture['service']->markReady($admin, $fixture['session']));
    $inject = $session->injects()->where('status', 'active')->firstOrFail();
    $fields = [
        'decision' => 'Isolasi akun terdampak.',
        'rationale' => 'Aktivitas masuk tidak dikenal.',
        'immediate_actions' => 'Cabut sesi aktif.',
        'coordination_handoff' => 'SOC meminta konfirmasi IT.',
    ];

    $created = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $inject->id,
        'team_id' => $fixture['operations']->id,
        'tenant_id' => 'spoofed',
        'submitted_by' => $fixture['otherUser']->id,
        ...$fields,
    ])->assertCreated()->json();

    foreach (['tenant_id', 'session_id', 'session_inject_id', 'submitted_by', 'last_edited_by', 'created_at', 'updated_at'] as $key) {
        expect($created)->not->toHaveKey($key);
    }
    expect($created['session_team_id'])->toBe($fixture['security']->id)
        ->and($created['revision'])->toBe(1)
        ->and($created['last_edited_by_name'])->toBe('Anggota SOC')
        ->and($created['locked_at'])->toBeNull();
    foreach ($fields as $key => $value) {
        expect($created[$key])->toBe($value);
    }
    expect($created)->toBe($this->getJson(route('tenant.ttx.sessions.show', $session))->assertOk()->json('injects.0.response'));

    $updated = $this->putJson(route('tenant.ttx.sessions.responses.update', [$session, $created['id']]), [
        'expected_revision' => $created['revision'],
        ...$fields,
        'decision' => 'Isolasi akun dan cabut sesi terdampak.',
        'team_id' => $fixture['operations']->id,
        'last_edited_by' => $fixture['otherUser']->id,
    ])->assertOk()->json();

    foreach (['tenant_id', 'session_id', 'session_inject_id', 'submitted_by', 'last_edited_by', 'created_at', 'updated_at'] as $key) {
        expect($updated)->not->toHaveKey($key);
    }
    expect($updated['session_team_id'])->toBe($fixture['security']->id)
        ->and($updated['revision'])->toBe(2)
        ->and($updated['decision'])->toBe('Isolasi akun dan cabut sesi terdampak.')
        ->and($updated['coordination_handoff'])->toBe($fields['coordination_handoff'])
        ->and($updated['last_edited_by_name'])->toBe('Anggota SOC');
    expect($updated)->toBe($this->getJson(route('tenant.ttx.sessions.show', $session))->assertOk()->json('injects.0.response'));
});

test('FINAL-B preparation provides historical context canonical capabilities structured ownership and exact gaps', function () {
    extract(finalBFixture());
    $service->removeResponsibilityAssignment($admin, $session, $primary->id);
    $payload = $service->preparationReadModel($admin, $session->fresh());

    expect($payload['exercise_context']['objectives'])->toBe('Validasi dan eskalasi.')
        ->and(array_column($payload['capabilities'], 'code'))->toBe(['EX-1', 'EX-2'])
        ->and($payload['capabilities'][0]['label'])->toBe('Detection & Triage')
        ->and($payload['playbook']['structured_phases'][0]['guidance'])->toBe('FACILITATOR PRIVATE GUIDANCE')
        ->and($payload['readiness']['uncovered_relevant_phase_keys'])->toBe(['detection_validation'])
        ->and($payload['readiness']['can_mark_ready'])->toBeFalse()
        ->and(json_encode($payload))->not->toContain('FUTURE INJECT TITLE', 'FUTURE INJECT NARRATIVE');
    $this->actingAs($admin)->postJson(route('tenant.ttx.sessions.ready', $session))->assertUnprocessable();
});

test('FINAL-B Primary replacement is atomic reuses the existing route and preserves server ownership', function () {
    extract(finalBFixture());
    $this->actingAs($admin)->putJson(route('tenant.ttx.sessions.responsibility-assignments.update', [$session, $primary]), [
        'role' => 'primary', 'team_id' => $operations->id, 'tenant_id' => 'spoofed', 'session_id' => 999999,
    ])->assertOk()->assertJsonPath('readiness.responsibility_coverage_complete', true);

    expect($primary->fresh()->session_team_id)->toBe($operations->id)
        ->and($primary->fresh()->tenant_id)->toBe($tenant->id)
        ->and($primary->fresh()->session_id)->toBe($session->id)
        ->and($session->responsibilityAssignments()->where('playbook_phase_key', 'detection_validation')->count())->toBe(1)
        ->and(AuditLog::where('action', 'ttx.responsibility_changed')->exists())->toBeTrue();
});

test('FINAL-B invalid replacement cannot remove the current Primary or emit a successful audit', function () {
    extract(finalBFixture());
    $otherSession = $service->create($admin, $exercise, 'Other session');
    $otherTeam = $service->createTeam($admin, $otherSession, 'Other SOC');
    $before = AuditLog::count();
    $this->actingAs($admin)->putJson(route('tenant.ttx.sessions.responsibility-assignments.update', [$session, $primary]), [
        'role' => 'primary', 'team_id' => $otherTeam->id,
    ])->assertNotFound();
    expect($primary->fresh()->session_team_id)->toBe($security->id)
        ->and(AuditLog::count())->toBe($before);
});

test('FINAL-B Primary replacement rejects wrong role other admin and foreign tenant', function (string $actorType) {
    extract(finalBFixture());
    $actor = match ($actorType) {
        'participant' => $participant,
        'other_admin' => User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]),
        'foreign_admin' => User::factory()->tenantAdmin()->create(['tenant_id' => Tenant::factory()->create()->id]),
    };
    $this->actingAs($actor)->putJson(route('tenant.ttx.sessions.responsibility-assignments.update', [$session, $primary]), [
        'role' => 'primary', 'team_id' => $operations->id,
    ])->assertForbidden();
    expect($primary->fresh()->session_team_id)->toBe($security->id);
})->with(['participant', 'other_admin', 'foreign_admin']);

test('FINAL-B ownership and notes stay immutable outside DRAFT', function (string $status) {
    extract(finalBFixture());
    $session->forceFill(['status' => $status])->save();
    $this->actingAs($admin)->putJson(route('tenant.ttx.sessions.responsibility-assignments.update', [$session, $primary]), [
        'role' => 'primary', 'team_id' => $operations->id,
    ])->assertForbidden();
    $this->putJson(route('tenant.ttx.sessions.teams.responsibilities.update', [$session, $security]), ['responsibilities' => 'Late notes'])->assertForbidden();
    expect($primary->fresh()->session_team_id)->toBe($security->id)->and($security->fresh()->responsibilities)->toBeNull();
})->with(['ready', 'in_progress', 'debrief', 'completed']);

test('FINAL-B READY briefing exposes only snapshot summaries own assignments and teammate names', function () {
    extract(finalBFixture());
    $service->markReady($admin, $session->fresh());
    $playbook->forceFill(['title' => 'MUTATED SOURCE', 'structured_phases' => null])->save();
    $payload = $this->actingAs($participant)->getJson(route('tenant.ttx.sessions.show', $session))->assertOk()->json();
    $phases = collect($payload['playbook']['structured_phases'])->keyBy('key');

    expect($payload['playbook']['title'])->toBe('Historical response baseline')
        ->and($phases['detection_validation']['team_role'])->toBe('primary')
        ->and($phases['escalation_ownership']['team_role'])->toBe('support')
        ->and($phases['detection_validation'])->not->toHaveKeys(['guidance', 'source_id'])
        ->and($payload['teams'][0]['participants'])->toBe([['name' => 'Anggota SOC'], ['name' => 'Rekan SOC']])
        ->and($payload['injects'])->toBeEmpty()
        ->and($payload['progress']['total'])->toBe(0)
        ->and(json_encode($payload))->not->toContain('PRIVATE GUIDANCE', 'PRIVATE LEGACY CONTENT', 'IRRELEVANT SUMMARY', 'FUTURE INJECT', 'Anggota Operations', 'MUTATED SOURCE');
});

test('FINAL-B V2 collaboration persists handoff safely and facilitator sees read-only decisions and waiting teams', function () {
    extract(finalBFixture());
    $service->markReady($admin, $session->fresh());
    $service->start($admin, $session->fresh());
    $inject = $session->injects()->where('order', 1)->sole();
    $valid = ['session_inject_id' => $inject->id, 'decision' => 'Contain account', 'rationale' => 'Confirmed suspicious activity', 'immediate_actions' => 'Revoke sessions', 'coordination_handoff' => 'SOC requests account recovery from Operations'];
    $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), array_replace($valid, ['coordination_handoff' => ' ']))
        ->assertUnprocessable()->assertJsonValidationErrors('coordination_handoff');
    $response = $this->postJson(route('tenant.ttx.sessions.responses.store', $session), $valid)->assertCreated()->json();
    $this->actingAs($teammate)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $response['id']]), array_replace($valid, ['expected_revision' => 1, 'coordination_handoff' => 'Updated handoff']))->assertOk();
    $payload = $this->getJson(route('tenant.ttx.sessions.show', $session))->assertOk()->json();
    expect($payload['injects'][0]['response']['coordination_handoff'])->toBe('Updated handoff')
        ->and($payload['injects'][0]['response']['last_edited_by_name'])->toBe('Rekan SOC')
        ->and($payload['injects'][0]['response'])->not->toHaveKeys(['last_edited_by', 'submitted_by', 'tenant_id'])
        ->and(json_encode($payload))->not->toContain('FUTURE INJECT');
    $otherPayload = $this->actingAs($otherUser)->getJson(route('tenant.ttx.sessions.show', $session))->assertOk()->json();
    expect($otherPayload['injects'][0]['response'])->toBeNull()->and(json_encode($otherPayload))->not->toContain('Updated handoff', 'Rekan SOC');
    $facilitatorPayload = $this->actingAs($admin)->getJson(route('tenant.ttx.sessions.show', $session))->assertOk()->json();
    expect($facilitatorPayload['injects'][0]['response_summary'])->toBe(['responded' => 1, 'total' => 2])
        ->and($facilitatorPayload['scenario_snapshot']['objectives'])->toBe('Validasi dan eskalasi.')
        ->and($facilitatorPayload['teams'][0]['responsibility_assignments'])->not->toBeEmpty();
    $this->postJson(route('tenant.ttx.sessions.responses.store', $session), $valid)->assertForbidden();
    $this->putJson(route('tenant.ttx.sessions.responses.update', [$session, $response['id']]), array_replace($valid, ['expected_revision' => 2]))->assertForbidden();
});

test('FINAL-B participant discovery hides assigned DRAFT sessions', function () {
    extract(finalBFixture());
    $this->actingAs($participant)->get(route('user.ttx.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('sessions', 0));
});
