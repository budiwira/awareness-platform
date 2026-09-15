<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function ddl()
    {
        // Match existing RLS migrations: DDL requires the table owner.
        $hasAdmin = (bool) config('database.connections.pgsql_admin.password');

        return DB::connection($hasAdmin ? 'pgsql_admin' : config('database.default'));
    }

    public function up(): void
    {
        $this->ddl()->statement("
            ALTER POLICY training_modules_select_policy ON training_modules
            USING (
                NULLIF(current_setting('app.role', true), '') = 'super_admin'
                OR (
                    is_active = true
                    AND (
                        tenant_id IS NULL
                        OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                    )
                )
            )
        ");
    }

    public function down(): void
    {
        $this->ddl()->statement("
            ALTER POLICY training_modules_select_policy ON training_modules
            USING (is_active = true OR NULLIF(current_setting('app.role', true), '') = 'super_admin')
        ");
    }
};
