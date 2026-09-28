<?php

use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxSession;
use App\Models\TtxSessionInject;
use App\Models\TtxSessionParticipant;
use App\Models\TtxSessionResponse;
use App\Models\TtxSessionTeam;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function createTtxResponseFixture(): array
{
    $tenant = Tenant::factory()->create();
    $creator = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $exercise = TtxExercise::create([
        'tenant_id' => $tenant->id,
        'title' => 'Response exercise',
        'scenario' => 'Response scenario',
    ]);

    $session = TtxSession::forceCreate([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'title' => 'Response session',
        'created_by' => $creator->id,
        'exercise_snapshot' => ['title' => $exercise->title, 'scenario' => $exercise->scenario],
    ]);

    $inject = TtxInject::create([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'order' => 1,
        'title' => 'Response inject',
        'description' => 'Inject for response',
    ]);

    $sessionInject = TtxSessionInject::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'inject_id' => $inject->id,
        'order' => 1,
        'status' => 'active',
        'inject_snapshot' => ['title' => $inject->title],
    ]);

    $submitter = User::factory()->create(['tenant_id' => $tenant->id]);
    $team = TtxSessionTeam::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'name' => 'Security / SOC',
    ]);
    TtxSessionParticipant::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'user_id' => $submitter->id,
        'team_id' => $team->id,
    ]);

    return [$tenant, $creator, $exercise, $session, $inject, $sessionInject, $submitter, $team];
}

function ttx_response_rls_pdo(): PDO
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

test('a valid same-tenant response can be persisted', function () {
    [, , , , , $sessionInject, $submitter] = createTtxResponseFixture();

    $response = TtxSessionResponse::forceCreate([
        'tenant_id' => $sessionInject->tenant_id,
        'session_id' => $sessionInject->session_id,
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Contain and investigate',
        'rationale' => 'Immediate containment limits blast radius.',
        'owner' => 'CSIRT Lead',
        'immediate_actions' => 'Isolate affected hosts.',
        'escalation' => 'Notify CISO within 30 minutes.',
        'unknowns' => 'Full scope of compromise.',
        'notes' => 'Pending forensic image.',
        'revision' => 1,
        'submitted_by' => $submitter->id,
        'submitted_at' => now(),
    ]);

    expect($response->fresh()->decision)->toBe('Contain and investigate')
        ->and($response->fresh()->revision)->toBe(1)
        ->and($response->fresh()->submitted_by)->toBe($submitter->id)
        ->and($response->fresh()->session->id)->toBe($sessionInject->session_id)
        ->and($response->fresh()->sessionInject->id)->toBe($sessionInject->id);
});

test('only one response can exist for a session inject and team', function () {
    [, , , , , $sessionInject, $submitter, $team] = createTtxResponseFixture();

    TtxSessionResponse::forceCreate([
        'tenant_id' => $sessionInject->tenant_id,
        'session_id' => $sessionInject->session_id,
        'session_inject_id' => $sessionInject->id,
        'session_team_id' => $team->id,
        'decision' => 'First response',
        'revision' => 1,
        'submitted_by' => $submitter->id,
        'submitted_at' => now(),
    ]);

    expect(fn () => DB::transaction(fn () => TtxSessionResponse::forceCreate([
        'tenant_id' => $sessionInject->tenant_id,
        'session_id' => $sessionInject->session_id,
        'session_inject_id' => $sessionInject->id,
        'session_team_id' => $team->id,
        'decision' => 'Second response',
        'revision' => 1,
        'submitted_by' => $submitter->id,
        'submitted_at' => now(),
    ])))->toThrow(QueryException::class);
});

test('cross-tenant session reference is rejected', function () {
    [, , , , , $sessionInject, $submitter] = createTtxResponseFixture();

    $otherTenant = Tenant::factory()->create();
    $otherCreator = User::factory()->tenantAdmin()->create(['tenant_id' => $otherTenant->id]);
    $otherExercise = TtxExercise::create([
        'tenant_id' => $otherTenant->id,
        'title' => 'Other exercise',
    ]);
    $otherSession = TtxSession::forceCreate([
        'tenant_id' => $otherTenant->id,
        'exercise_id' => $otherExercise->id,
        'title' => 'Other session',
        'created_by' => $otherCreator->id,
        'exercise_snapshot' => ['title' => $otherExercise->title],
    ]);

    expect(fn () => DB::transaction(fn () => TtxSessionResponse::forceCreate([
        'tenant_id' => $sessionInject->tenant_id,
        'session_id' => $otherSession->id,
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Cross-tenant attempt',
        'revision' => 1,
        'submitted_by' => $submitter->id,
        'submitted_at' => now(),
    ])))->toThrow(QueryException::class);
});

test('cross-tenant session-inject reference is rejected', function () {
    [, $creator, $exercise, $session, , $sessionInject, $submitter] = createTtxResponseFixture();

    $otherTenant = Tenant::factory()->create();
    $otherCreator = User::factory()->tenantAdmin()->create(['tenant_id' => $otherTenant->id]);
    $otherExercise = TtxExercise::create([
        'tenant_id' => $otherTenant->id,
        'title' => 'Other exercise',
    ]);
    $otherSession = TtxSession::forceCreate([
        'tenant_id' => $otherTenant->id,
        'exercise_id' => $otherExercise->id,
        'title' => 'Other session',
        'created_by' => $otherCreator->id,
        'exercise_snapshot' => ['title' => $otherExercise->title],
    ]);
    $otherInject = TtxInject::create([
        'tenant_id' => $otherTenant->id,
        'exercise_id' => $otherExercise->id,
        'order' => 1,
        'title' => 'Other inject',
    ]);
    $otherSessionInject = TtxSessionInject::forceCreate([
        'tenant_id' => $otherTenant->id,
        'session_id' => $otherSession->id,
        'inject_id' => $otherInject->id,
        'order' => 1,
        'status' => 'active',
        'inject_snapshot' => ['title' => $otherInject->title],
    ]);

    expect(fn () => DB::transaction(fn () => TtxSessionResponse::forceCreate([
        'tenant_id' => $sessionInject->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $otherSessionInject->id,
        'decision' => 'Cross-tenant inject attempt',
        'revision' => 1,
        'submitted_by' => $submitter->id,
        'submitted_at' => now(),
    ])))->toThrow(QueryException::class);
});

test('same-tenant inject belonging to a different session is rejected by DB invariant', function () {
    [, , , $session, , $sessionInject, $submitter] = createTtxResponseFixture();

    // Create a second session in the SAME tenant with its own exercise.
    $otherExercise = TtxExercise::create([
        'tenant_id' => $session->tenant_id,
        'title' => 'Other exercise same tenant',
    ]);
    $otherSession = TtxSession::forceCreate([
        'tenant_id' => $session->tenant_id,
        'exercise_id' => $otherExercise->id,
        'title' => 'Other session same tenant',
        'created_by' => $session->created_by,
        'exercise_snapshot' => ['title' => 'Other'],
    ]);

    // Create an inject template and session-inject for the OTHER session.
    $otherInject = TtxInject::create([
        'tenant_id' => $session->tenant_id,
        'exercise_id' => $otherExercise->id,
        'order' => 1,
        'title' => 'Other inject same tenant',
    ]);
    $otherSessionInject = TtxSessionInject::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $otherSession->id,
        'inject_id' => $otherInject->id,
        'order' => 1,
        'status' => 'active',
        'inject_snapshot' => ['title' => $otherInject->title],
    ]);

    // Attempt to create a response: session_id = Session A, session_inject_id = inject from Session B.
    // Both belong to the same tenant. The composite FK must reject this.
    expect(fn () => DB::transaction(fn () => TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $otherSessionInject->id,
        'decision' => 'Same-tenant cross-session mismatch',
        'revision' => 1,
        'submitted_by' => $submitter->id,
        'submitted_at' => now(),
    ])))->toThrow(QueryException::class);
});

test('cross-tenant submitted_by is rejected', function () {
    [, , , , , $sessionInject, $submitter] = createTtxResponseFixture();

    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

    expect(fn () => DB::transaction(fn () => TtxSessionResponse::forceCreate([
        'tenant_id' => $sessionInject->tenant_id,
        'session_id' => $sessionInject->session_id,
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Wrong tenant submitter',
        'revision' => 1,
        'submitted_by' => $otherUser->id,
        'submitted_at' => now(),
    ])))->toThrow(QueryException::class);
});

test('revision defaults to 1', function () {
    [, , , , , $sessionInject, $submitter] = createTtxResponseFixture();

    $response = TtxSessionResponse::forceCreate([
        'tenant_id' => $sessionInject->tenant_id,
        'session_id' => $sessionInject->session_id,
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Default revision',
        'submitted_by' => $submitter->id,
        'submitted_at' => now(),
    ]);

    expect($response->fresh()->revision)->toBe(1);
});

test('revision < 1 is rejected', function () {
    [, , , , , $sessionInject, $submitter] = createTtxResponseFixture();

    expect(fn () => DB::transaction(fn () => TtxSessionResponse::forceCreate([
        'tenant_id' => $sessionInject->tenant_id,
        'session_id' => $sessionInject->session_id,
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Invalid revision',
        'revision' => 0,
        'submitted_by' => $submitter->id,
        'submitted_at' => now(),
    ])))->toThrow(QueryException::class);
});

test('decision longer than 255 characters can be persisted', function () {
    [, , , , , $sessionInject, $submitter] = createTtxResponseFixture();

    $longDecision = str_repeat('A', 300);

    $response = TtxSessionResponse::forceCreate([
        'tenant_id' => $sessionInject->tenant_id,
        'session_id' => $sessionInject->session_id,
        'session_inject_id' => $sessionInject->id,
        'decision' => $longDecision,
        'revision' => 1,
        'submitted_by' => $submitter->id,
        'submitted_at' => now(),
    ]);

    expect($response->fresh()->decision)->toBe($longDecision)
        ->and(strlen($response->fresh()->decision))->toBe(300);
});

test('PostgreSQL RLS isolates response rows by tenant', function () {
    $tenantA = Tenant::on('pgsql_owner')->create(['name' => 'RLS response A', 'slug' => 'rls-response-a-'.uniqid()]);
    $tenantB = Tenant::on('pgsql_owner')->create(['name' => 'RLS response B', 'slug' => 'rls-response-b-'.uniqid()]);

    $creatorA = User::on('pgsql_owner')->create([
        'name' => 'Response creator A',
        'email' => 'response-creator-a-'.uniqid().'@rls.test',
        'password' => 'password',
        'role' => 'tenant_admin',
        'tenant_id' => $tenantA->id,
        'is_active' => true,
    ]);
    $creatorB = User::on('pgsql_owner')->create([
        'name' => 'Response creator B',
        'email' => 'response-creator-b-'.uniqid().'@rls.test',
        'password' => 'password',
        'role' => 'tenant_admin',
        'tenant_id' => $tenantB->id,
        'is_active' => true,
    ]);

    $exerciseA = TtxExercise::on('pgsql_owner')->create(['tenant_id' => $tenantA->id, 'title' => 'RLS response exercise A']);
    $exerciseB = TtxExercise::on('pgsql_owner')->create(['tenant_id' => $tenantB->id, 'title' => 'RLS response exercise B']);

    $sessionA = TtxSession::on('pgsql_owner')->forceCreate([
        'tenant_id' => $tenantA->id,
        'exercise_id' => $exerciseA->id,
        'title' => 'RLS response session A',
        'created_by' => $creatorA->id,
        'exercise_snapshot' => ['title' => $exerciseA->title],
    ]);
    $sessionB = TtxSession::on('pgsql_owner')->forceCreate([
        'tenant_id' => $tenantB->id,
        'exercise_id' => $exerciseB->id,
        'title' => 'RLS response session B',
        'created_by' => $creatorB->id,
        'exercise_snapshot' => ['title' => $exerciseB->title],
    ]);

    $injectA = TtxInject::on('pgsql_owner')->create([
        'tenant_id' => $tenantA->id,
        'exercise_id' => $exerciseA->id,
        'order' => 1,
        'title' => 'RLS inject A',
    ]);
    $injectB = TtxInject::on('pgsql_owner')->create([
        'tenant_id' => $tenantB->id,
        'exercise_id' => $exerciseB->id,
        'order' => 1,
        'title' => 'RLS inject B',
    ]);

    $sessionInjectA = TtxSessionInject::on('pgsql_owner')->forceCreate([
        'tenant_id' => $tenantA->id,
        'session_id' => $sessionA->id,
        'inject_id' => $injectA->id,
        'order' => 1,
        'status' => 'active',
        'inject_snapshot' => ['title' => $injectA->title],
    ]);
    $sessionInjectB = TtxSessionInject::on('pgsql_owner')->forceCreate([
        'tenant_id' => $tenantB->id,
        'session_id' => $sessionB->id,
        'inject_id' => $injectB->id,
        'order' => 1,
        'status' => 'active',
        'inject_snapshot' => ['title' => $injectB->title],
    ]);

    $responseA = TtxSessionResponse::on('pgsql_owner')->forceCreate([
        'tenant_id' => $tenantA->id,
        'session_id' => $sessionA->id,
        'session_inject_id' => $sessionInjectA->id,
        'decision' => 'RLS response A',
        'revision' => 1,
        'submitted_by' => $creatorA->id,
        'submitted_at' => now(),
    ]);
    $responseB = TtxSessionResponse::on('pgsql_owner')->forceCreate([
        'tenant_id' => $tenantB->id,
        'session_id' => $sessionB->id,
        'session_inject_id' => $sessionInjectB->id,
        'decision' => 'RLS response B',
        'revision' => 1,
        'submitted_by' => $creatorB->id,
        'submitted_at' => now(),
    ]);

    $pdo = ttx_response_rls_pdo();

    try {
        $pdo->exec("SELECT set_config('app.tenant_id', '{$tenantA->id}', false)");
        $pdo->exec("SELECT set_config('app.role', 'tenant_admin', false)");

        $visible = array_map('intval', $pdo->query('SELECT id FROM ttx_session_responses ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        expect($visible)->toContain($responseA->id)->not->toContain($responseB->id);
    } finally {
        TtxSessionResponse::on('pgsql_owner')->whereIn('id', [$responseA->id, $responseB->id])->delete();
        TtxSessionInject::on('pgsql_owner')->whereIn('id', [$sessionInjectA->id, $sessionInjectB->id])->delete();
        TtxInject::on('pgsql_owner')->whereIn('id', [$injectA->id, $injectB->id])->delete();
        TtxSession::on('pgsql_owner')->whereIn('id', [$sessionA->id, $sessionB->id])->delete();
        TtxExercise::on('pgsql_owner')->whereIn('id', [$exerciseA->id, $exerciseB->id])->delete();
        User::on('pgsql_owner')->whereIn('id', [$creatorA->id, $creatorB->id])->forceDelete();
        Tenant::on('pgsql_owner')->whereIn('id', [$tenantA->id, $tenantB->id])->delete();
        $pdo = null;
    }
});

test('sensitive ownership and integrity fields cannot be overwritten through mass assignment', function () {
    $response = new TtxSessionResponse;

    expect($response->getFillable())->not->toContain('tenant_id')
        ->and($response->getFillable())->not->toContain('session_id')
        ->and($response->getFillable())->not->toContain('session_inject_id')
        ->and($response->getFillable())->not->toContain('session_team_id')
        ->and($response->getFillable())->not->toContain('submitted_by')
        ->and($response->getFillable())->not->toContain('submitted_at')
        ->and($response->getFillable())->not->toContain('last_edited_by')
        ->and($response->getFillable())->not->toContain('locked_at')
        ->and($response->getFillable())->not->toContain('revision');
});

test('cross-tenant last_edited_by is rejected when set', function () {
    [, , , , , $sessionInject, $submitter] = createTtxResponseFixture();

    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

    $response = TtxSessionResponse::forceCreate([
        'tenant_id' => $sessionInject->tenant_id,
        'session_id' => $sessionInject->session_id,
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Editor test',
        'revision' => 1,
        'submitted_by' => $submitter->id,
        'submitted_at' => now(),
    ]);

    expect(fn () => DB::transaction(fn () => DB::table('ttx_session_responses')
        ->where('id', $response->id)
        ->update(['last_edited_by' => $otherUser->id])
    ))->toThrow(QueryException::class);
});

test('last_edited_by may be NULL', function () {
    [, , , , , $sessionInject, $submitter] = createTtxResponseFixture();

    $response = TtxSessionResponse::forceCreate([
        'tenant_id' => $sessionInject->tenant_id,
        'session_id' => $sessionInject->session_id,
        'session_inject_id' => $sessionInject->id,
        'decision' => 'No editor yet',
        'revision' => 1,
        'submitted_by' => $submitter->id,
        'submitted_at' => now(),
    ]);

    expect($response->fresh()->last_edited_by)->toBeNull();
});

test('valid same-tenant last_edited_by may be stored', function () {
    [, , , , , $sessionInject, $submitter] = createTtxResponseFixture();

    $editor = User::factory()->create(['tenant_id' => $sessionInject->tenant_id]);

    $response = TtxSessionResponse::forceCreate([
        'tenant_id' => $sessionInject->tenant_id,
        'session_id' => $sessionInject->session_id,
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Edited response',
        'revision' => 1,
        'submitted_by' => $submitter->id,
        'submitted_at' => now(),
    ]);

    DB::table('ttx_session_responses')
        ->where('id', $response->id)
        ->update(['last_edited_by' => $editor->id]);

    expect($response->fresh()->last_edited_by)->toBe($editor->id);
});

test('hard-deleting a user referenced by last_edited_by is blocked by DB FK', function () {
    [, , , , , $sessionInject, $submitter] = createTtxResponseFixture();

    $editor = User::factory()->create(['tenant_id' => $sessionInject->tenant_id]);

    $response = TtxSessionResponse::forceCreate([
        'tenant_id' => $sessionInject->tenant_id,
        'session_id' => $sessionInject->session_id,
        'session_inject_id' => $sessionInject->id,
        'decision' => 'Response with editor',
        'revision' => 1,
        'submitted_by' => $submitter->id,
        'submitted_at' => now(),
        'last_edited_by' => $editor->id,
    ]);

    // Hard delete must be blocked — the composite FK RESTRICTs it.
    expect(fn () => DB::transaction(fn () => $editor->forceDelete()))
        ->toThrow(QueryException::class);

    // Verify the user and response still exist.
    expect(User::withTrashed()->find($editor->id))->not->toBeNull()
        ->and($response->fresh()->last_edited_by)->toBe($editor->id);
});
