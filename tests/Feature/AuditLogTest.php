<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;

function audit_pdo(): PDO
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

test('successful login writes an audit log', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'auth.login',
        'actor_user_id' => $user->id,
    ]);
});

test('app role cannot update or delete audit logs (db-level immutability)', function () {
    AuditLog::create(['action' => 'test.event', 'created_at' => now()]);

    $pdo = audit_pdo();

    try {
        $pdo->exec("UPDATE audit_logs SET action = 'tampered'");
        throw new RuntimeException('UPDATE seharusnya ditolak database');
    } catch (PDOException $e) {
        expect($e->getCode())->toBe('42501');
    }

    try {
        $pdo->exec('DELETE FROM audit_logs');
        throw new RuntimeException('DELETE seharusnya ditolak database');
    } catch (PDOException $e) {
        expect($e->getCode())->toBe('42501');
    }
});

test('audit logs are tenant-scoped via RLS', function () {
    $tenantA = Tenant::on('pgsql_owner')->create(['name' => 'Aud A', 'slug' => 'aud-a-'.uniqid()]);
    $tenantB = Tenant::on('pgsql_owner')->create(['name' => 'Aud B', 'slug' => 'aud-b-'.uniqid()]);

    try {
        AuditLog::on('pgsql_owner')->create(['tenant_id' => $tenantA->id, 'action' => 'a.event', 'created_at' => now()]);
        AuditLog::on('pgsql_owner')->create(['tenant_id' => $tenantB->id, 'action' => 'b.event', 'created_at' => now()]);

        $pdo = audit_pdo();
        $pdo->exec("SELECT set_config('app.tenant_id', '{$tenantA->id}', false)");

        $actions = $pdo->query('SELECT action FROM audit_logs')->fetchAll(PDO::FETCH_COLUMN);

        expect($actions)->toContain('a.event')->not->toContain('b.event');
    } finally {
        // WAJIB: data committed di koneksi owner tidak di-rollback otomatis.
        AuditLog::on('pgsql_owner')->whereIn('tenant_id', [$tenantA->id, $tenantB->id])->delete();
        Tenant::on('pgsql_owner')->whereIn('id', [$tenantA->id, $tenantB->id])->delete();
    }
});
