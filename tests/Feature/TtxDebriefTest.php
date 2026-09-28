<?php

use App\Enums\TtxSessionInjectStatus;
use App\Enums\TtxSessionRole;
use App\Enums\TtxSessionStatus;
use App\Models\Tenant;
use App\Models\TtxActionItem;
use App\Models\TtxAfterActionSummary;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxPlaybook;
use App\Models\TtxSession;
use App\Models\TtxSessionEvaluation;
use App\Models\TtxSessionInject;
use App\Models\TtxSessionParticipant;
use App\Models\TtxSessionResponse;
use App\Models\TtxSessionTeam;
use App\Models\User;
use App\Services\TtxSessionService;
use Inertia\Testing\AssertableInertia as Assert;

function ttxDebriefFixture(): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $facilitator = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $participant = User::factory()->create(['tenant_id' => $tenant->id]);
    $exercise = TtxExercise::create([
        'tenant_id' => $tenant->id,
        'title' => 'Exercise debrief',
        'scenario' => 'Insiden untuk debrief',
    ]);
    $playbook = TtxPlaybook::create([
        'tenant_id' => $tenant->id,
        'title' => 'Credential Compromise Response Playbook',
        'description' => 'Panduan respons organisasi.',
        'content' => "1. Detection & Validation\n2. Escalation & Ownership",
        'is_active' => true,
    ]);
    $exercise->update(['playbook_id' => $playbook->id]);
    $inject = TtxInject::create([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'playbook_id' => $playbook->id,
        'order' => 1,
        'title' => 'Inject final',
        'description' => 'Situasi final',
    ]);
    $session = TtxSession::forceCreate([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'title' => 'Sesi debrief',
        'created_by' => $facilitator->id,
        'status' => TtxSessionStatus::Debrief,
        'started_at' => now()->subHour(),
        'debrief_started_at' => now(),
        'exercise_snapshot' => ['title' => $exercise->title],
        'playbook_snapshot' => ['source_id' => $playbook->id, 'title' => $playbook->title, 'description' => $playbook->description, 'content' => $playbook->content],
    ]);
    TtxSessionParticipant::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'user_id' => $facilitator->id,
        'session_role' => TtxSessionRole::Facilitator,
    ]);
    $team = TtxSessionTeam::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'name' => 'Security / SOC',
        'responsibilities' => 'Validasi alert dan tentukan cakupan insiden.',
    ]);
    TtxSessionParticipant::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'user_id' => $participant->id,
        'team_id' => $team->id,
        'session_role' => TtxSessionRole::Security,
    ]);
    $sessionInject = TtxSessionInject::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'inject_id' => $inject->id,
        'order' => 1,
        'status' => TtxSessionInjectStatus::Locked,
        'inject_snapshot' => [
            'title' => $inject->title,
            'description' => $inject->description,
            'facilitator_notes' => 'Catatan internal rahasia',
        ],
        'released_at' => now()->subMinutes(30),
        'locked_at' => now(),
        'released_by' => $facilitator->id,
    ]);
    TtxSessionResponse::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'session_inject_id' => $sessionInject->id,
        'session_team_id' => $team->id,
        'decision' => 'Respons resmi tim',
        'notes' => 'Catatan respons',
        'revision' => 1,
        'submitted_by' => $participant->id,
        'submitted_at' => now(),
        'locked_at' => now(),
    ]);

    return compact('tenant', 'admin', 'facilitator', 'participant', 'exercise', 'session', 'sessionInject');
}

function completeEvaluationPayload(): array
{
    return collect(array_keys(TtxSessionService::EVALUATION_DIMENSIONS))
        ->map(fn (string $dimension) => [
            'dimension' => $dimension,
            'rating' => 'effective',
            'evidence' => "Bukti internal {$dimension}",
        ])->all();
}

function completeAarPayload(): array
{
    return [
        'overall_summary' => 'Tim menangani exercise dengan alur yang terkoordinasi.',
        'strengths' => 'Eskalasi cepat dan kepemilikan jelas.',
        'improvement_areas' => 'Dokumentasi keputusan perlu lebih konsisten.',
        'key_lessons' => 'Tetapkan kanal komunikasi insiden sejak awal.',
    ];
}

function ttxDebriefRlsPdo(): PDO
{
    return new PDO(
        sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            config('database.connections.pgsql.host'),
            config('database.connections.pgsql.port'),
            config('database.connections.pgsql.database'),
        ),
        env('DB_APP_USERNAME'),
        env('DB_APP_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
}

test('only the explicit active facilitator can open and manage debrief', function () {
    $fixture = ttxDebriefFixture();

    $this->actingAs($fixture['facilitator'])
        ->get(route('tenant.ttx.sessions.debrief', $fixture['session']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Ttx/Sessions/Debrief')
            ->where('sessionId', $fixture['session']->id));

    $actors = [
        $fixture['participant'],
        User::factory()->tenantAdmin()->create(['tenant_id' => $fixture['tenant']->id]),
        User::factory()->superAdmin()->create(),
        User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]),
    ];

    foreach ($actors as $actor) {
        $this->actingAs($actor)
            ->putJson(route('tenant.ttx.sessions.evaluation.update', $fixture['session']), [
                'evaluations' => [completeEvaluationPayload()[0]],
            ])->assertForbidden();
    }

    $fixture['facilitator']->update(['is_active' => false]);
    $this->actingAs($fixture['facilitator']->fresh())
        ->putJson(route('tenant.ttx.sessions.aar.update', $fixture['session']), completeAarPayload())
        ->assertForbidden();
});

test('facilitator records the six qualitative dimensions without numeric scoring', function () {
    $fixture = ttxDebriefFixture();

    $this->actingAs($fixture['facilitator'])
        ->putJson(route('tenant.ttx.sessions.evaluation.update', $fixture['session']), [
            'evaluations' => completeEvaluationPayload(),
        ])->assertOk()->assertJsonCount(6, 'dimensions');

    expect($fixture['session']->evaluations()->count())->toBe(6)
        ->and($fixture['session']->evaluations()->pluck('dimension')->sort()->values()->all())
        ->toBe(collect(array_keys(TtxSessionService::EVALUATION_DIMENSIONS))->sort()->values()->all());
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.evaluation_updated', 'subject_id' => (string) $fixture['session']->id]);

    $this->actingAs($fixture['facilitator'])
        ->putJson(route('tenant.ttx.sessions.evaluation.update', $fixture['session']), [
            'evaluations' => [[
                'dimension' => 'detection_triage',
                'rating' => 95,
                'evidence' => 'Numeric score is forbidden',
            ]],
        ])->assertUnprocessable();

    $this->actingAs($fixture['facilitator'])
        ->putJson(route('tenant.ttx.sessions.evaluation.update', $fixture['session']), [
            'evaluations' => [[
                'dimension' => 'detection_triage',
                'rating' => 'excellent',
            ]],
        ])->assertUnprocessable();
});

test('facilitator creates and updates action items while preventing IDOR', function () {
    $fixture = ttxDebriefFixture();
    $payload = ['title' => 'Perbarui playbook', 'owner' => 'SOC Lead', 'priority' => 'high', 'due_date' => '2026-10-10', 'status' => 'open'];

    $created = $this->actingAs($fixture['facilitator'])
        ->postJson(route('tenant.ttx.sessions.action-items.store', $fixture['session']), $payload)
        ->assertCreated();
    $item = TtxActionItem::findOrFail($created->json('id'));

    $this->actingAs($fixture['facilitator'])
        ->putJson(route('tenant.ttx.sessions.action-items.update', [$fixture['session'], $item]), [
            ...$payload, 'status' => 'completed',
        ])->assertOk()->assertJsonPath('status', 'completed');
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.action_item_completed', 'subject_id' => (string) $item->id]);

    $otherSession = TtxSession::forceCreate([
        'tenant_id' => $fixture['tenant']->id,
        'exercise_id' => $fixture['exercise']->id,
        'title' => 'Sesi debrief lain',
        'created_by' => $fixture['admin']->id,
        'status' => TtxSessionStatus::Debrief,
        'exercise_snapshot' => ['title' => $fixture['exercise']->title],
    ]);
    $otherItem = TtxActionItem::forceCreate([
        'tenant_id' => $fixture['tenant']->id,
        'session_id' => $otherSession->id,
        ...$payload,
        'created_by' => $fixture['facilitator']->id,
        'updated_by' => $fixture['facilitator']->id,
    ]);
    $this->actingAs($fixture['facilitator'])
        ->putJson(route('tenant.ttx.sessions.action-items.update', [$fixture['session'], $otherItem]), $payload)
        ->assertNotFound();

    $this->actingAs($fixture['participant'])
        ->postJson(route('tenant.ttx.sessions.action-items.store', $fixture['session']), $payload)
        ->assertForbidden();

    $crossTenantUser = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $this->actingAs($crossTenantUser)
        ->postJson(route('tenant.ttx.sessions.action-items.store', $fixture['session']), $payload)
        ->assertForbidden();
});

test('after-action summary is facilitator-owned and debrief mutations become immutable after completion', function () {
    $fixture = ttxDebriefFixture();
    $actionPayload = ['title' => 'Action sebelum final', 'owner' => 'SOC Lead', 'priority' => 'medium', 'due_date' => null, 'status' => 'open'];

    $this->actingAs($fixture['participant'])
        ->putJson(route('tenant.ttx.sessions.aar.update', $fixture['session']), completeAarPayload())
        ->assertForbidden();

    $this->actingAs($fixture['facilitator'])
        ->putJson(route('tenant.ttx.sessions.aar.update', $fixture['session']), completeAarPayload())
        ->assertOk();
    $this->actingAs($fixture['facilitator'])
        ->putJson(route('tenant.ttx.sessions.evaluation.update', $fixture['session']), ['evaluations' => completeEvaluationPayload()])
        ->assertOk();
    $actionId = $this->actingAs($fixture['facilitator'])
        ->postJson(route('tenant.ttx.sessions.action-items.store', $fixture['session']), $actionPayload)
        ->assertCreated()
        ->json('id');
    $this->actingAs($fixture['facilitator'])
        ->postJson(route('tenant.ttx.sessions.complete', $fixture['session']))
        ->assertOk()->assertJsonPath('session.status', 'completed');

    expect($fixture['session']->fresh()->completed_at)->not->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.session_completed', 'subject_id' => (string) $fixture['session']->id]);

    $this->actingAs($fixture['facilitator'])
        ->putJson(route('tenant.ttx.sessions.aar.update', $fixture['session']), completeAarPayload())
        ->assertUnprocessable();
    $this->actingAs($fixture['facilitator'])
        ->putJson(route('tenant.ttx.sessions.evaluation.update', $fixture['session']), ['evaluations' => completeEvaluationPayload()])
        ->assertUnprocessable();
    $this->actingAs($fixture['facilitator'])
        ->postJson(route('tenant.ttx.sessions.action-items.store', $fixture['session']), [
            'title' => 'Tidak boleh', 'owner' => 'Owner', 'priority' => 'low', 'due_date' => null, 'status' => 'open',
        ])->assertUnprocessable();
    $this->actingAs($fixture['facilitator'])
        ->putJson(route('tenant.ttx.sessions.action-items.update', [$fixture['session'], $actionId]), [
            ...$actionPayload, 'status' => 'completed',
        ])->assertUnprocessable();
    $this->actingAs($fixture['facilitator'])
        ->postJson(route('tenant.ttx.sessions.complete', $fixture['session']))
        ->assertUnprocessable();
});

test('completion requires all six ratings and the complete after-action summary', function () {
    $fixture = ttxDebriefFixture();

    $this->actingAs($fixture['facilitator'])
        ->postJson(route('tenant.ttx.sessions.complete', $fixture['session']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('evaluation');

    $this->actingAs($fixture['facilitator'])
        ->putJson(route('tenant.ttx.sessions.evaluation.update', $fixture['session']), ['evaluations' => completeEvaluationPayload()])
        ->assertOk();
    $this->actingAs($fixture['facilitator'])
        ->postJson(route('tenant.ttx.sessions.complete', $fixture['session']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('after_action_summary');

    $this->actingAs($fixture['facilitator'])
        ->putJson(route('tenant.ttx.sessions.aar.update', $fixture['session']), completeAarPayload())
        ->assertOk();
    $this->actingAs($fixture['facilitator'])
        ->postJson(route('tenant.ttx.sessions.complete', $fixture['session']))
        ->assertOk();
});

test('participant completed payload exposes only the allowlisted final summary', function () {
    $fixture = ttxDebriefFixture();
    $this->actingAs($fixture['facilitator'])
        ->putJson(route('tenant.ttx.sessions.evaluation.update', $fixture['session']), ['evaluations' => completeEvaluationPayload()]);
    $this->actingAs($fixture['facilitator'])
        ->putJson(route('tenant.ttx.sessions.aar.update', $fixture['session']), completeAarPayload());
    $this->actingAs($fixture['facilitator'])
        ->postJson(route('tenant.ttx.sessions.complete', $fixture['session']))->assertOk();

    $payload = $this->actingAs($fixture['participant'])
        ->getJson(route('tenant.ttx.sessions.show', $fixture['session']))
        ->assertOk()
        ->assertJsonPath('outcome.overall_summary', completeAarPayload()['overall_summary'])
        ->json();

    expect($payload['outcome'])->toHaveKeys(['overall_summary', 'strengths', 'improvement_areas', 'key_lessons'])
        ->and($payload['participant_team']['responsibilities'])->toContain('Validasi alert')
        ->and($payload['playbook'])->not->toHaveKeys(['content', 'source_id'])
        ->and($payload['outcome'])->not->toHaveKeys(['evidence', 'rating', 'updated_by']);
    $json = json_encode($payload);
    expect($json)
        ->not->toContain('Bukti internal')
        ->not->toContain('Catatan internal rahasia')
        ->not->toContain('action_items')
        ->not->toContain('dimensions');
});

test('facilitator debrief compares playbook and team preparation with actual responses', function () {
    $fixture = ttxDebriefFixture();

    $payload = $this->actingAs($fixture['facilitator'])
        ->getJson(route('tenant.ttx.sessions.debrief', $fixture['session']))
        ->assertOk()
        ->json();

    expect($payload['session']['playbook']['title'])->toBe('Credential Compromise Response Playbook')
        ->and($payload['session']['playbook']['content'])->toContain('Escalation & Ownership')
        ->and($payload['session']['teams'][0]['responsibilities'])->toContain('Validasi alert')
        ->and($payload['session']['injects'][0]['team_responses'][0]['response']['decision'])->toBe('Respons resmi tim');
});

test('completed session has dedicated facilitator and participant result pages', function () {
    $fixture = ttxDebriefFixture();
    $fixture['session']->update([
        'status' => TtxSessionStatus::Completed,
        'completed_at' => now(),
    ]);

    $this->actingAs($fixture['facilitator'])
        ->get(route('tenant.ttx.sessions.result', $fixture['session']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Ttx/Sessions/Debrief')
            ->where('sessionId', $fixture['session']->id)
            ->where('resultMode', true));

    $this->actingAs($fixture['participant'])
        ->get(route('tenant.ttx.sessions.participant-result', $fixture['session']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Ttx/Sessions/ParticipantWorkspace')
            ->where('sessionId', $fixture['session']->id)
            ->where('resultMode', true));

    $this->actingAs($fixture['admin'])
        ->get(route('tenant.ttx.sessions.result', $fixture['session']))
        ->assertForbidden();
});

test('result pages reject sessions that are not completed', function () {
    $fixture = ttxDebriefFixture();

    $this->actingAs($fixture['facilitator'])
        ->get(route('tenant.ttx.sessions.result', $fixture['session']))
        ->assertStatus(409);

    $this->actingAs($fixture['participant'])
        ->get(route('tenant.ttx.sessions.participant-result', $fixture['session']))
        ->assertStatus(409);
});

test('debrief tables enforce PostgreSQL tenant isolation', function () {
    $tenantA = Tenant::on('pgsql_owner')->create(['name' => 'Debrief RLS A', 'slug' => 'debrief-rls-a-'.uniqid()]);
    $tenantB = Tenant::on('pgsql_owner')->create(['name' => 'Debrief RLS B', 'slug' => 'debrief-rls-b-'.uniqid()]);
    $userA = User::on('pgsql_owner')->create(['name' => 'Debrief A', 'email' => 'debrief-a-'.uniqid().'@test.local', 'password' => 'password', 'role' => 'tenant_admin', 'tenant_id' => $tenantA->id, 'is_active' => true]);
    $userB = User::on('pgsql_owner')->create(['name' => 'Debrief B', 'email' => 'debrief-b-'.uniqid().'@test.local', 'password' => 'password', 'role' => 'tenant_admin', 'tenant_id' => $tenantB->id, 'is_active' => true]);
    $exerciseA = TtxExercise::on('pgsql_owner')->create(['tenant_id' => $tenantA->id, 'title' => 'Debrief RLS exercise A']);
    $exerciseB = TtxExercise::on('pgsql_owner')->create(['tenant_id' => $tenantB->id, 'title' => 'Debrief RLS exercise B']);
    $sessionA = TtxSession::on('pgsql_owner')->forceCreate(['tenant_id' => $tenantA->id, 'exercise_id' => $exerciseA->id, 'title' => 'Debrief RLS session A', 'created_by' => $userA->id, 'status' => 'debrief', 'exercise_snapshot' => ['title' => 'A']]);
    $sessionB = TtxSession::on('pgsql_owner')->forceCreate(['tenant_id' => $tenantB->id, 'exercise_id' => $exerciseB->id, 'title' => 'Debrief RLS session B', 'created_by' => $userB->id, 'status' => 'debrief', 'exercise_snapshot' => ['title' => 'B']]);
    $evaluationA = TtxSessionEvaluation::on('pgsql_owner')->forceCreate(['tenant_id' => $tenantA->id, 'session_id' => $sessionA->id, 'dimension' => 'detection_triage', 'rating' => 'effective', 'updated_by' => $userA->id]);
    $evaluationB = TtxSessionEvaluation::on('pgsql_owner')->forceCreate(['tenant_id' => $tenantB->id, 'session_id' => $sessionB->id, 'dimension' => 'detection_triage', 'rating' => 'strong', 'updated_by' => $userB->id]);
    $actionA = TtxActionItem::on('pgsql_owner')->forceCreate(['tenant_id' => $tenantA->id, 'session_id' => $sessionA->id, 'title' => 'Action A', 'owner' => 'Owner A', 'priority' => 'medium', 'status' => 'open', 'created_by' => $userA->id, 'updated_by' => $userA->id]);
    $actionB = TtxActionItem::on('pgsql_owner')->forceCreate(['tenant_id' => $tenantB->id, 'session_id' => $sessionB->id, 'title' => 'Action B', 'owner' => 'Owner B', 'priority' => 'medium', 'status' => 'open', 'created_by' => $userB->id, 'updated_by' => $userB->id]);
    $aarA = TtxAfterActionSummary::on('pgsql_owner')->forceCreate(['tenant_id' => $tenantA->id, 'session_id' => $sessionA->id, ...completeAarPayload(), 'updated_by' => $userA->id]);
    $aarB = TtxAfterActionSummary::on('pgsql_owner')->forceCreate(['tenant_id' => $tenantB->id, 'session_id' => $sessionB->id, ...completeAarPayload(), 'updated_by' => $userB->id]);
    $pdo = ttxDebriefRlsPdo();

    try {
        $pdo->exec("SELECT set_config('app.tenant_id', '{$tenantA->id}', false)");

        foreach ([
            'ttx_session_evaluations' => [$evaluationA->id, $evaluationB->id],
            'ttx_action_items' => [$actionA->id, $actionB->id],
            'ttx_after_action_summaries' => [$aarA->id, $aarB->id],
        ] as $table => [$visibleId, $hiddenId]) {
            $ids = array_map('intval', $pdo->query("SELECT id FROM {$table} ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
            expect($ids)->toContain($visibleId)->not->toContain($hiddenId);
        }
    } finally {
        TtxAfterActionSummary::on('pgsql_owner')->whereIn('id', [$aarA->id, $aarB->id])->delete();
        TtxActionItem::on('pgsql_owner')->whereIn('id', [$actionA->id, $actionB->id])->delete();
        TtxSessionEvaluation::on('pgsql_owner')->whereIn('id', [$evaluationA->id, $evaluationB->id])->delete();
        TtxSession::on('pgsql_owner')->whereIn('id', [$sessionA->id, $sessionB->id])->delete();
        TtxExercise::on('pgsql_owner')->whereIn('id', [$exerciseA->id, $exerciseB->id])->delete();
        User::on('pgsql_owner')->whereIn('id', [$userA->id, $userB->id])->forceDelete();
        Tenant::on('pgsql_owner')->whereIn('id', [$tenantA->id, $tenantB->id])->delete();
        $pdo = null;
    }
});

test('debrief ownership and tenant fields are guarded from mass assignment', function () {
    expect((new TtxSessionEvaluation)->getFillable())
        ->not->toContain('tenant_id', 'session_id', 'dimension', 'updated_by')
        ->and((new TtxActionItem)->getFillable())
        ->not->toContain('tenant_id', 'session_id', 'created_by', 'updated_by')
        ->and((new TtxAfterActionSummary)->getFillable())
        ->not->toContain('tenant_id', 'session_id', 'updated_by');
});

test('FINAL-C V1 keeps six required ratings nullable findings and canonical action capabilities', function () {
    $fixture = ttxDebriefFixture();
    $fixture['session']->forceFill(['exercise_snapshot' => ['title' => 'Legacy', 'capability_codes' => ['EX-1']], 'response_contract_version' => 1])->save();
    $payload = $this->actingAs($fixture['facilitator'])->getJson(route('tenant.ttx.sessions.debrief', $fixture['session']))->assertOk()->json();
    expect(collect($payload['dimensions'])->where('required', true))->toHaveCount(6);
    $this->postJson(route('tenant.ttx.sessions.action-items.store', $fixture['session']), [
        'title' => 'Legacy recovery action', 'owner' => 'IT Lead', 'priority' => 'medium', 'status' => 'open', 'capability_code' => 'EX-6',
    ])->assertCreated()->assertJsonPath('category', null);
    $this->putJson(route('tenant.ttx.sessions.evaluation.update', $fixture['session']), ['evaluations' => completeEvaluationPayload()])->assertOk();
    $this->putJson(route('tenant.ttx.sessions.aar.update', $fixture['session']), completeAarPayload())->assertOk();
    $this->postJson(route('tenant.ttx.sessions.complete', $fixture['session']))->assertOk();
});

test('FINAL-C all facilitator writes reject participants other admins platform and foreign admins', function (string $kind) {
    $fixture = ttxDebriefFixture();
    $actor = match ($kind) {
        'participant' => $fixture['participant'],
        'other_admin' => $fixture['admin'],
        'platform' => User::factory()->superAdmin()->create(),
        'foreign_admin' => User::factory()->tenantAdmin()->create(['tenant_id' => Tenant::factory()->create()->id]),
    };
    $item = TtxActionItem::forceCreate(['tenant_id' => $fixture['tenant']->id, 'session_id' => $fixture['session']->id, 'title' => 'Protected action', 'owner' => 'SOC', 'priority' => 'medium', 'status' => 'open', 'created_by' => $fixture['facilitator']->id, 'updated_by' => $fixture['facilitator']->id]);
    $this->actingAs($actor);
    foreach ([
        ['put', 'evaluation.update', ['evaluations' => completeEvaluationPayload()]],
        ['post', 'action-items.store', ['title' => 'Not allowed', 'owner' => 'SOC', 'priority' => 'medium', 'status' => 'open']],
        ['put', 'aar.update', completeAarPayload()],
        ['post', 'complete', []],
    ] as [$verb, $route, $payload]) {
        $this->{$verb.'Json'}(route('tenant.ttx.sessions.'.$route, $fixture['session']), $payload)->assertForbidden();
    }
    $this->putJson(route('tenant.ttx.sessions.action-items.update', [$fixture['session'], $item]), ['title' => 'Not allowed', 'owner' => 'SOC', 'priority' => 'medium', 'status' => 'open'])->assertForbidden();
    expect($item->fresh()->title)->toBe('Protected action')->and($fixture['session']->evaluations()->count())->toBe(0);
})->with(['participant', 'other_admin', 'platform', 'foreign_admin']);
