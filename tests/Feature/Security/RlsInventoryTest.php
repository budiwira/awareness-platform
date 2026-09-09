<?php

use Illuminate\Support\Facades\DB;

test('jaring rls: setiap tabel tenant-scoped memiliki RLS aktif dan policy', function () {
    $tables = DB::select(
        "SELECT DISTINCT table_name
         FROM information_schema.columns
         WHERE column_name = 'tenant_id' AND table_schema = 'public'
         ORDER BY table_name"
    );

    expect($tables)->not->toBeEmpty('Tidak ada tabel tenant-scoped terdeteksi - cek DB test.');

    $violations = [];

    foreach ($tables as $t) {
        $rel = DB::select(
            "SELECT relrowsecurity FROM pg_class WHERE relname = ? AND relkind = 'r'",
            [$t->table_name]
        );

        if (empty($rel) || ! $rel[0]->relrowsecurity) {
            $violations[] = $t->table_name.': RLS tidak aktif';

            continue;
        }

        $policies = DB::select(
            "SELECT policyname FROM pg_policies WHERE schemaname = 'public' AND tablename = ?",
            [$t->table_name]
        );

        if (empty($policies)) {
            $violations[] = $t->table_name.': tidak ada policy';
        }
    }

    expect($violations)->toBe([], 'TABEL TANPA PROTEKSI: '.implode('; ', $violations));
});
