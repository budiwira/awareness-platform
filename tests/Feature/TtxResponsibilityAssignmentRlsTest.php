<?php

use App\Enums\TtxSessionStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxSession;
use App\Models\TtxSessionParticipant;
use App\Models\TtxSessionResponsibilityAssignment;
use App\Models\TtxSessionTeam;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function ttxResponsibilityRlsPdo(): PDO
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

function ttxResponsibilityRlsFixture(): array
{
    $tenant = Tenant::on('pgsql_owner')->create(['name' => 'TTX responsibility RLS', 'slug' => 'ttx-responsibility-'.uniqid()]);
    $foreignTenant = Tenant::on('pgsql_owner')->create(['name' => 'Foreign TTX responsibility', 'slug' => 'ttx-responsibility-foreign-'.uniqid()]);
    $admin = User::on('pgsql_owner')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Creator Admin',
        'email' => 'creator-'.uniqid().'@rls.test',
        'password' => 'password',
        'role' => UserRole::TenantAdmin,
        'is_active' => true,
    ]);
    $otherAdmin = User::on('pgsql_owner')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Other Admin',
        'email' => 'other-admin-'.uniqid().'@rls.test',
        'password' => 'password',
        'role' => UserRole::TenantAdmin,
        'is_active' => true,
    ]);
    $securityUser = User::on('pgsql_owner')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Security User',
        'email' => 'security-responsibility-'.uniqid().'@rls.test',
        'password' => 'password',
        'role' => UserRole::User,
        'is_active' => true,
    ]);
    $operationsUser = User::on('pgsql_owner')->create([
        'tenant_id' => $tenant->id,
        'name' => 'Operations User',
        'email' => 'operations-responsibility-'.uniqid().'@rls.test',
        'password' => 'password',
        'role' => UserRole::User,
        'is_active' => true,
    ]);
    $exercise = TtxExercise::on('pgsql_owner')->create([
        'tenant_id' => $tenant->id,
        'title' => 'Responsibility RLS Exercise',
        'scenario' => 'RLS scenario.',
    ]);
    $session = (new TtxSession)->setConnection('pgsql_owner');
    $session->forceFill([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'title' => 'Responsibility RLS Session',
        'created_by' => $admin->id,
        'status' => TtxSessionStatus::Draft,
        'exercise_snapshot' => ['title' => 'RLS Exercise', 'scenario' => 'RLS scenario.'],
    ])->save();
    $securityTeam = TtxSessionTeam::on('pgsql_owner')->forceCreate([
        'tenant_id' => $tenant->id, 'session_id' => $session->id, 'name' => 'Security',
    ]);
    $operationsTeam = TtxSessionTeam::on('pgsql_owner')->forceCreate([
        'tenant_id' => $tenant->id, 'session_id' => $session->id, 'name' => 'Operations',
    ]);
    foreach ([[$securityUser, $securityTeam], [$operationsUser, $operationsTeam]] as [$user, $team]) {
        $participant = (new TtxSessionParticipant)->setConnection('pgsql_owner');
        $participant->forceFill([
            'tenant_id' => $tenant->id,
            'session_id' => $session->id,
            'user_id' => $user->id,
            'team_id' => $team->id,
        ])->save();
    }
    $assignments = collect([
        [$securityTeam, 'detection_validation', 'primary'],
        [$operationsTeam, 'detection_validation', 'support'],
    ])->map(function (array $entry) use ($tenant, $session) {
        [$team, $phase, $role] = $entry;
        $assignment = (new TtxSessionResponsibilityAssignment)->setConnection('pgsql_owner');
        $assignment->forceFill([
            'tenant_id' => $tenant->id,
            'session_id' => $session->id,
            'session_team_id' => $team->id,
            'playbook_phase_key' => $phase,
            'role' => $role,
        ])->save();

        return $assignment;
    });

    return compact(
        'tenant', 'foreignTenant', 'admin', 'otherAdmin', 'securityUser', 'operationsUser',
        'exercise', 'session', 'securityTeam', 'operationsTeam', 'assignments'
    );
}

function cleanupTtxResponsibilityRlsFixture(array $fixture): void
{
    $owner = DB::connection('pgsql_owner');
    $owner->table('ttx_session_responsibility_assignments')->where('session_id', $fixture['session']->id)->delete();
    $owner->table('ttx_session_participants')->where('session_id', $fixture['session']->id)->delete();
    $owner->table('ttx_session_teams')->where('session_id', $fixture['session']->id)->delete();
    $owner->table('ttx_sessions')->where('id', $fixture['session']->id)->delete();
    $owner->table('ttx_exercises')->where('id', $fixture['exercise']->id)->delete();
    User::on('pgsql_owner')->whereIn('id', [
        $fixture['admin']->id,
        $fixture['otherAdmin']->id,
        $fixture['securityUser']->id,
        $fixture['operationsUser']->id,
    ])->forceDelete();
    Tenant::on('pgsql_owner')->whereIn('id', [$fixture['tenant']->id, $fixture['foreignTenant']->id])->delete();
}

test('responsibility assignment RLS limits participants to own team and mutations to creator admin', function () {
    $fixture = ttxResponsibilityRlsFixture();

    try {
        $pdo = ttxResponsibilityRlsPdo();
        $pdo->exec("SELECT set_config('app.tenant_id', '{$fixture['tenant']->id}', false)");
        $pdo->exec("SELECT set_config('app.user_id', '{$fixture['securityUser']->id}', false)");
        $pdo->exec("SELECT set_config('app.role', 'user', false)");

        $visible = $pdo->query('SELECT id FROM ttx_session_responsibility_assignments ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        expect(array_map('intval', $visible))->toBe([$fixture['assignments'][0]->id]);

        $pdo->exec("SELECT set_config('app.user_id', '{$fixture['otherAdmin']->id}', false)");
        $pdo->exec("SELECT set_config('app.role', 'tenant_admin', false)");
        expect((int) $pdo->query('SELECT COUNT(*) FROM ttx_session_responsibility_assignments')->fetchColumn())->toBe(2);
        $forbiddenDelete = $pdo->prepare('DELETE FROM ttx_session_responsibility_assignments WHERE id = ?');
        $forbiddenDelete->execute([$fixture['assignments'][0]->id]);
        expect($forbiddenDelete->rowCount())->toBe(0);

        $pdo->exec("SELECT set_config('app.user_id', '{$fixture['admin']->id}', false)");
        $creatorUpdate = $pdo->prepare('UPDATE ttx_session_responsibility_assignments SET role = ? WHERE id = ?');
        $creatorUpdate->execute(['support', $fixture['assignments'][0]->id]);
        expect($creatorUpdate->rowCount())->toBe(1);

        $pdo->exec("SELECT set_config('app.tenant_id', '{$fixture['foreignTenant']->id}', false)");
        expect((int) $pdo->query('SELECT COUNT(*) FROM ttx_session_responsibility_assignments')->fetchColumn())->toBe(0);
    } finally {
        cleanupTtxResponsibilityRlsFixture($fixture);
    }
});
