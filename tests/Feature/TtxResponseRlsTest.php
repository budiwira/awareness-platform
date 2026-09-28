<?php

use App\Enums\TtxSessionInjectStatus;
use App\Enums\TtxSessionStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxSession;
use App\Models\TtxSessionInject;
use App\Models\TtxSessionParticipant;
use App\Models\TtxSessionResponse;
use App\Models\TtxSessionTeam;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function ttxResponseRlsPdo(): PDO
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

function ttxResponseRlsFixture(): array
{
    $tenant = Tenant::on('pgsql_owner')->create(['name' => 'TTX response RLS', 'slug' => 'ttx-response-rls-'.uniqid()]);
    $admin = User::on('pgsql_owner')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Facilitator',
        'email' => 'facilitator-'.uniqid().'@rls.test',
        'password' => 'password',
        'role' => UserRole::TenantAdmin,
        'is_active' => true,
    ]);
    $securityUser = User::on('pgsql_owner')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Security learner',
        'email' => 'security-'.uniqid().'@rls.test',
        'password' => 'password',
        'role' => UserRole::User,
        'is_active' => true,
    ]);
    $operationsUser = User::on('pgsql_owner')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Operations learner',
        'email' => 'operations-'.uniqid().'@rls.test',
        'password' => 'password',
        'role' => UserRole::User,
        'is_active' => true,
    ]);
    $exercise = TtxExercise::on('pgsql_owner')->create([
        'tenant_id' => $tenant->id,
        'title' => 'RLS exercise',
        'scenario' => 'RLS scenario',
    ]);
    $inject = TtxInject::on('pgsql_owner')->create([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'order' => 1,
        'title' => 'RLS inject',
        'description' => 'RLS inject description',
    ]);
    $session = (new TtxSession)->setConnection('pgsql_owner');
    $session->forceFill([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'title' => 'RLS session',
        'created_by' => $admin->id,
        'status' => TtxSessionStatus::InProgress,
        'started_at' => now(),
        'exercise_snapshot' => ['title' => 'RLS exercise'],
    ])->save();
    $securityTeam = TtxSessionTeam::on('pgsql_owner')->forceCreate(['tenant_id' => $tenant->id, 'session_id' => $session->id, 'name' => 'Security']);
    $operationsTeam = TtxSessionTeam::on('pgsql_owner')->forceCreate(['tenant_id' => $tenant->id, 'session_id' => $session->id, 'name' => 'Operations']);
    foreach ([[$securityUser, $securityTeam], [$operationsUser, $operationsTeam]] as [$user, $team]) {
        $participant = (new TtxSessionParticipant)->setConnection('pgsql_owner');
        $participant->forceFill([
            'tenant_id' => $tenant->id,
            'session_id' => $session->id,
            'user_id' => $user->id,
            'team_id' => $team->id,
        ])->save();
    }
    $sessionInject = (new TtxSessionInject)->setConnection('pgsql_owner');
    $sessionInject->forceFill([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'inject_id' => $inject->id,
        'order' => 1,
        'status' => TtxSessionInjectStatus::Active,
        'inject_snapshot' => ['title' => 'RLS inject'],
        'released_at' => now(),
        'released_by' => $admin->id,
    ])->save();
    $responses = collect([[$securityUser, $securityTeam, 'Security response'], [$operationsUser, $operationsTeam, 'Operations response']])
        ->map(function (array $entry) use ($tenant, $session, $sessionInject) {
            [$user, $team, $decision] = $entry;
            $response = (new TtxSessionResponse)->setConnection('pgsql_owner');
            $response->forceFill([
                'tenant_id' => $tenant->id,
                'session_id' => $session->id,
                'session_inject_id' => $sessionInject->id,
                'session_team_id' => $team->id,
                'decision' => $decision,
                'submitted_by' => $user->id,
                'submitted_at' => now(),
            ])->save();

            return $response;
        });

    return compact('tenant', 'admin', 'securityUser', 'operationsUser', 'exercise', 'inject', 'session', 'securityTeam', 'operationsTeam', 'sessionInject', 'responses');
}

function cleanupTtxResponseRlsFixture(array $fixture): void
{
    $owner = DB::connection('pgsql_owner');
    $owner->table('ttx_session_responses')->where('session_id', $fixture['session']->id)->delete();
    $owner->table('ttx_session_injects')->where('session_id', $fixture['session']->id)->delete();
    $owner->table('ttx_session_participants')->where('session_id', $fixture['session']->id)->delete();
    $owner->table('ttx_session_teams')->where('session_id', $fixture['session']->id)->delete();
    $owner->table('ttx_sessions')->where('id', $fixture['session']->id)->delete();
    $owner->table('ttx_injects')->where('id', $fixture['inject']->id)->delete();
    $owner->table('ttx_exercises')->where('id', $fixture['exercise']->id)->delete();
    User::on('pgsql_owner')->whereIn('id', [
        $fixture['admin']->id,
        $fixture['securityUser']->id,
        $fixture['operationsUser']->id,
    ])->forceDelete();
    Tenant::on('pgsql_owner')->whereKey($fixture['tenant']->id)->delete();
}

test('response RLS isolates team reads and mutations while allowing facilitator locks only', function () {
    $fixture = ttxResponseRlsFixture();

    try {
        $securityResponse = $fixture['responses'][0];
        $operationsResponse = $fixture['responses'][1];
        $pdo = ttxResponseRlsPdo();
        $pdo->exec("SELECT set_config('app.tenant_id', '{$fixture['tenant']->id}', false)");
        $pdo->exec("SELECT set_config('app.user_id', '{$fixture['securityUser']->id}', false)");
        $pdo->exec("SELECT set_config('app.role', 'user', false)");

        $visibleIds = $pdo->query('SELECT id FROM ttx_session_responses ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        expect(array_map('intval', $visibleIds))->toBe([$securityResponse->id]);

        $crossTeamUpdate = $pdo->prepare('UPDATE ttx_session_responses SET decision = ?, last_edited_by = ?, revision = revision + 1 WHERE id = ?');
        $crossTeamUpdate->execute(['Tampered', $fixture['securityUser']->id, $operationsResponse->id]);
        expect($crossTeamUpdate->rowCount())->toBe(0);

        $ownUpdate = $pdo->prepare('UPDATE ttx_session_responses SET decision = ?, last_edited_by = ?, revision = revision + 1 WHERE id = ?');
        $ownUpdate->execute(['Updated by Security', $fixture['securityUser']->id, $securityResponse->id]);
        expect($ownUpdate->rowCount())->toBe(1);

        $pdo->exec("SELECT set_config('app.user_id', '{$fixture['admin']->id}', false)");
        $pdo->exec("SELECT set_config('app.role', 'tenant_admin', false)");
        expect((int) $pdo->query('SELECT COUNT(*) FROM ttx_session_responses')->fetchColumn())->toBe(2);

        $facilitatorNarrativeUpdate = $pdo->prepare('UPDATE ttx_session_responses SET decision = ? WHERE id = ?');
        expect(fn () => $facilitatorNarrativeUpdate->execute(['Facilitator edit', $securityResponse->id]))
            ->toThrow(PDOException::class);

        $pdo = ttxResponseRlsPdo();
        $pdo->exec("SELECT set_config('app.tenant_id', '{$fixture['tenant']->id}', false)");
        $pdo->exec("SELECT set_config('app.user_id', '{$fixture['admin']->id}', false)");
        $pdo->exec("SELECT set_config('app.role', 'tenant_admin', false)");
        $lock = $pdo->prepare('UPDATE ttx_session_responses SET locked_at = NOW() WHERE id = ?');
        $lock->execute([$securityResponse->id]);
        expect($lock->rowCount())->toBe(1);
    } finally {
        cleanupTtxResponseRlsFixture($fixture);
    }
});
