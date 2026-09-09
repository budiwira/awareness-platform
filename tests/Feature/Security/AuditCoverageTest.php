<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('jaring audit: tabel audit_logs ada dan terlindungi RLS', function () {
    expect(DB::select("SELECT 1 FROM pg_tables WHERE tablename = 'audit_logs' AND schemaname = 'public'"))
        ->not->toBeEmpty('Tabel audit_logs tidak ditemukan - observability dasar tidak tersedia.');
});

test('jaring audit: login menulis baris audit', function () {
    DB::table('audit_logs')->truncate();

    $user = User::factory()->create([
        'role' => UserRole::TenantAdmin,
        'password' => bcrypt('password'),
    ]);

    $before = DB::table('audit_logs')->count();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $after = DB::table('audit_logs')->count();

    expect($after)->toBeGreaterThan($before, 'Login sukses tidak menulis audit log.');
});
