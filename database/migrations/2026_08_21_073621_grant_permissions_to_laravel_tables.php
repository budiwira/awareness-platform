<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Grant explicit permissions untuk semua tabel Laravel standard
        // yang mungkin dibuat sebelum migration RLS kita
        $tables = [
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'password_reset_tokens',
        ];

        foreach ($tables as $table) {
            try {
                DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$table} TO awareness_app");
            } catch (Exception $e) {
                // Tabel mungkin belum ada, skip
            }
        }

        // Grant untuk sequences (jika ada)
        try {
            DB::statement('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO awareness_app');
        } catch (Exception $e) {
            // Skip jika error
        }
    }

    public function down(): void
    {
        // No rollback needed
    }
};
