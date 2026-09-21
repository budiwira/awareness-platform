<?php

use App\Enums\TtxSessionInjectStatus;
use App\Enums\TtxSessionRole;
use App\Enums\TtxSessionStatus;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxSession;
use App\Models\TtxSessionInject;
use App\Models\TtxSessionParticipant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function createTtxSessionFixture(): array
{
    $tenant = Tenant::factory()->create();
    $creator = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $exercise = TtxExercise::create([
        'tenant_id' => $tenant->id,
        'title' => 'Session exercise',
        'scenario' => 'Scenario snapshot',
    ]);

    $session = TtxSession::forceCreate([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'title' => 'Operational response',
        'created_by' => $creator->id,
        'exercise_snapshot' => ['title' => $exercise->title, 'scenario' => $exercise->scenario],
    ]);

    return [$tenant, $creator, $exercise, $session];
}

function createTtxSessionInjectFixture(TtxSession $session): TtxInject
{
    return TtxInject::create([
        'tenant_id' => $session->tenant_id,
        'exercise_id' => $session->exercise_id,
        'order' => 1,
        'title' => 'Initial inject',
        'description' => 'Inject content snapshot source',
    ]);
}

function ttx_rls_pdo(): PDO
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

test('an exercise can have multiple sessions with independent tenant and exercise ownership', function () {
    [$tenant, $creator, $exercise, $first] = createTtxSessionFixture();
    $second = TtxSession::forceCreate([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'title' => 'Second execution',
        'created_by' => $creator->id,
        'exercise_snapshot' => ['title' => 'Second snapshot'],
    ]);

    expect($exercise->fresh()->sessions)->toHaveCount(2)
        ->and($first->fresh()->tenant_id)->toBe($tenant->id)
        ->and($first->fresh()->exercise->is($exercise))->toBeTrue()
        ->and($second->fresh()->status)->toBe(TtxSessionStatus::Draft);
});

test('session roles are cast and duplicate user assignments are rejected', function () {
    [$tenant, , , $session] = createTtxSessionFixture();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    TtxSessionParticipant::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'user_id' => $user->id,
        'session_role' => TtxSessionRole::Security,
    ]);

    expect($session->fresh()->participants->first()->session_role)->toBe(TtxSessionRole::Security);

    expect(fn () => DB::transaction(fn () => TtxSessionParticipant::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'user_id' => $user->id,
        'session_role' => TtxSessionRole::Management,
    ])))->toThrow(QueryException::class);
});

test('a session permits one facilitator and multiple non-facilitator members', function () {
    [$tenant, , , $session] = createTtxSessionFixture();
    $users = User::factory()->count(3)->create(['tenant_id' => $tenant->id]);

    TtxSessionParticipant::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'user_id' => $users[0]->id,
        'session_role' => TtxSessionRole::Facilitator,
    ]);
    foreach ([$users[1], $users[2]] as $user) {
        TtxSessionParticipant::forceCreate([
            'tenant_id' => $tenant->id,
            'session_id' => $session->id,
            'user_id' => $user->id,
            'session_role' => TtxSessionRole::Security,
        ]);
    }

    expect(fn () => DB::transaction(fn () => TtxSessionParticipant::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'user_id' => User::factory()->create(['tenant_id' => $tenant->id])->id,
        'session_role' => TtxSessionRole::Facilitator,
    ])))->toThrow(QueryException::class);
    expect($session->fresh()->participants)->toHaveCount(3);
});

test('session injects snapshot their template and enforce unique and active invariants', function () {
    [$tenant, , , $session] = createTtxSessionFixture();
    $otherSession = TtxSession::forceCreate([
        'tenant_id' => $tenant->id,
        'exercise_id' => $session->exercise_id,
        'title' => 'Other execution',
        'created_by' => $session->created_by,
        'exercise_snapshot' => ['title' => 'Other'],
    ]);
    $inject = createTtxSessionInjectFixture($session);
    $secondInject = TtxInject::create([
        'tenant_id' => $tenant->id,
        'exercise_id' => $session->exercise_id,
        'order' => 2,
        'title' => 'Second inject',
    ]);

    $first = TtxSessionInject::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'inject_id' => $inject->id,
        'order' => 1,
        'status' => TtxSessionInjectStatus::Active,
        'inject_snapshot' => ['title' => $inject->title],
    ]);
    expect($first->fresh()->inject->is($inject))->toBeTrue()
        ->and($first->fresh()->inject_snapshot)->toBe(['title' => 'Initial inject'])
        ->and($first->fresh()->status)->toBe(TtxSessionInjectStatus::Active);

    expect(fn () => DB::transaction(fn () => TtxSessionInject::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'inject_id' => $inject->id,
        'order' => 1,
        'status' => TtxSessionInjectStatus::Pending,
        'inject_snapshot' => [],
    ])))->toThrow(QueryException::class);

    expect(fn () => DB::transaction(fn () => TtxSessionInject::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'inject_id' => $secondInject->id,
        'order' => 2,
        'status' => TtxSessionInjectStatus::Active,
        'inject_snapshot' => ['title' => $secondInject->title],
    ])))->toThrow(QueryException::class);

    TtxSessionInject::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $otherSession->id,
        'inject_id' => $secondInject->id,
        'order' => 1,
        'status' => TtxSessionInjectStatus::Active,
        'inject_snapshot' => ['title' => $secondInject->title],
    ]);
});

test('new execution tables isolate tenants through PostgreSQL RLS', function () {
    $tenantA = Tenant::on('pgsql_owner')->create(['name' => 'RLS session A', 'slug' => 'rls-session-a-'.uniqid()]);
    $tenantB = Tenant::on('pgsql_owner')->create(['name' => 'RLS session B', 'slug' => 'rls-session-b-'.uniqid()]);
    $creatorA = User::on('pgsql_owner')->create([
        'name' => 'Session creator A',
        'email' => 'session-creator-a-'.uniqid().'@rls.test',
        'password' => 'password',
        'role' => 'tenant_admin',
        'tenant_id' => $tenantA->id,
        'is_active' => true,
    ]);
    $creatorB = User::on('pgsql_owner')->create([
        'name' => 'Session creator B',
        'email' => 'session-creator-b-'.uniqid().'@rls.test',
        'password' => 'password',
        'role' => 'tenant_admin',
        'tenant_id' => $tenantB->id,
        'is_active' => true,
    ]);
    $exerciseA = TtxExercise::on('pgsql_owner')->create(['tenant_id' => $tenantA->id, 'title' => 'RLS exercise A']);
    $exerciseB = TtxExercise::on('pgsql_owner')->create(['tenant_id' => $tenantB->id, 'title' => 'RLS exercise B']);
    $sessionA = TtxSession::on('pgsql_owner')->forceCreate([
        'tenant_id' => $tenantA->id,
        'exercise_id' => $exerciseA->id,
        'title' => 'RLS session A',
        'created_by' => $creatorA->id,
        'exercise_snapshot' => ['title' => 'RLS exercise A'],
    ]);
    $sessionB = TtxSession::on('pgsql_owner')->forceCreate([
        'tenant_id' => $tenantB->id,
        'exercise_id' => $exerciseB->id,
        'title' => 'RLS session B',
        'created_by' => $creatorB->id,
        'exercise_snapshot' => ['title' => 'RLS exercise B'],
    ]);
    $pdo = ttx_rls_pdo();

    try {
        $pdo->exec("SELECT set_config('app.tenant_id', '{$tenantA->id}', false)");

        $visible = array_map('intval', $pdo->query('SELECT id FROM ttx_sessions ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        expect($visible)->toContain($sessionA->id)->not->toContain($sessionB->id);
        expect((int) $pdo->query('SELECT COUNT(*) FROM ttx_session_participants')->fetchColumn())->toBe(0);
        expect((int) $pdo->query('SELECT COUNT(*) FROM ttx_session_injects')->fetchColumn())->toBe(0);
    } finally {
        TtxSession::on('pgsql_owner')->whereIn('id', [$sessionA->id, $sessionB->id])->delete();
        TtxExercise::on('pgsql_owner')->whereIn('id', [$exerciseA->id, $exerciseB->id])->delete();
        User::on('pgsql_owner')->whereIn('id', [$creatorA->id, $creatorB->id])->forceDelete();
        Tenant::on('pgsql_owner')->whereIn('id', [$tenantA->id, $tenantB->id])->delete();
        $pdo = null;
    }
});
