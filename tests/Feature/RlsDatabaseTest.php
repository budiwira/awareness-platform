<?php

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;

function rls_pdo(): PDO
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

// Fixture dibuat lewat koneksi OWNER agar langsung COMMIT,
// sehingga terlihat dari sesi PDO terpisah (awareness_app).
function rls_create_fixtures(): array
{
    $tenantA = Tenant::on('pgsql_owner')->create(['name' => 'RLS A', 'slug' => 'rls-a-'.uniqid()]);
    $tenantB = Tenant::on('pgsql_owner')->create(['name' => 'RLS B', 'slug' => 'rls-b-'.uniqid()]);

    $alice = User::on('pgsql_owner')->create([
        'name' => 'Alice A',
        'email' => 'alice-'.uniqid().'@rls.test',
        'password' => 'password',
        'role' => UserRole::User,
        'tenant_id' => $tenantA->id,
        'is_active' => true,
    ]);

    $bob = User::on('pgsql_owner')->create([
        'name' => 'Bob B',
        'email' => 'bob-'.uniqid().'@rls.test',
        'password' => 'password',
        'role' => UserRole::User,
        'tenant_id' => $tenantB->id,
        'is_active' => true,
    ]);

    return [$tenantA, $tenantB, $alice, $bob];
}

function rls_cleanup(array $fixtures): void
{
    [$tenantA, $tenantB, $alice, $bob] = $fixtures;

    // Soft delete users dulu (dengan withTrashed untuk forceDelete)
    User::on('pgsql_owner')->whereIn('id', [$alice->id, $bob->id])->forceDelete();
    Tenant::on('pgsql_owner')->whereIn('id', [$tenantA->id, $tenantB->id])->delete();
}

test('RLS: tanpa context, role app tidak melihat satu baris pun', function () {
    $pdo = rls_pdo();
    $rows = $pdo->query('SELECT * FROM tenants')->fetchAll();

    expect($rows)->toBe([]);
});

test('RLS: context tenant A tidak melihat data tenant B, walau WHERE dipaksa', function () {
    $fixtures = rls_create_fixtures();

    try {
        [$tenantA, $tenantB] = $fixtures;

        $pdo = rls_pdo();
        $pdo->exec("SELECT set_config('app.tenant_id', '{$tenantA->id}', false)");

        $names = $pdo->query('SELECT name FROM users')->fetchAll(PDO::FETCH_COLUMN);
        expect($names)->toContain('Alice A')->not->toContain('Bob B');

        $leak = $pdo->query("SELECT name FROM users WHERE tenant_id = '{$tenantB->id}'")
            ->fetchAll(PDO::FETCH_COLUMN);
        expect($leak)->toBe([]);
    } finally {
        rls_cleanup($fixtures);
    }
});

test('RLS: super admin context melihat seluruh tenants', function () {
    $fixtures = rls_create_fixtures();

    try {
        $pdo = rls_pdo();
        $pdo->exec("SELECT set_config('app.role', 'super_admin', false)");

        $count = $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();

        expect((int) $count)->toBe(2);
    } finally {
        rls_cleanup($fixtures);
    }
});