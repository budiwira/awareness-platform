<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLES = [
        'ctf_challenges', 'case_studies', 'case_scenes',
        'training_modules', 'quizzes', 'quiz_questions',
        'packages', 'subscriptions',
    ];

    private function ddl()
    {
        // DDL butuh owner tabel: pakai koneksi admin bila dikonfigurasi,
        // fallback ke koneksi default (mis. di test env yang ownernya sudah benar).
        $hasAdmin = (bool) config('database.connections.pgsql_admin.password');

        return DB::connection($hasAdmin ? 'pgsql_admin' : config('database.default'));
    }

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->ddl()->statement("DROP POLICY IF EXISTS {$table}_super_update ON {$table}");
            $this->ddl()->statement("DROP POLICY IF EXISTS {$table}_super_delete ON {$table}");
            $this->ddl()->statement("CREATE POLICY {$table}_super_update ON {$table} FOR UPDATE USING (NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");
            $this->ddl()->statement("CREATE POLICY {$table}_super_delete ON {$table} FOR DELETE USING (NULLIF(current_setting('app.role', true), '') = 'super_admin')");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            $this->ddl()->statement("DROP POLICY IF EXISTS {$table}_super_update ON {$table}");
            $this->ddl()->statement("DROP POLICY IF EXISTS {$table}_super_delete ON {$table}");
        }
    }
};