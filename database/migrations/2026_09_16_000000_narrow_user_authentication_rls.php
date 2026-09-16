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
        $this->ddl()->statement('DROP POLICY IF EXISTS users_auth_lookup ON users');
        $this->ddl()->statement('DROP POLICY IF EXISTS users_tenant_isolation ON users');

        $this->ddl()->statement("
            CREATE POLICY users_tenant_isolation ON users
            FOR ALL
            USING (
                id = NULLIF(current_setting('app.user_id', true), '')::bigint
                OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR NULLIF(current_setting('app.role', true), '') = 'super_admin'
            )
            WITH CHECK (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR NULLIF(current_setting('app.role', true), '') = 'super_admin'
            )
        ");

        $this->ddl()->statement("
            CREATE POLICY users_auth_lookup ON users
            FOR SELECT
            USING (
                email = NULLIF(current_setting('app.auth_email', true), '')
            )
        ");
    }

    public function down(): void
    {
        $this->ddl()->statement('DROP POLICY IF EXISTS users_auth_lookup ON users');
        $this->ddl()->statement('DROP POLICY IF EXISTS users_tenant_isolation ON users');

        $this->ddl()->statement("
            CREATE POLICY users_tenant_isolation ON users
            FOR ALL
            USING (
                id = NULLIF(current_setting('app.user_id', true), '')::bigint
                OR tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR NULLIF(current_setting('app.role', true), '') = 'super_admin'
                OR NULLIF(current_setting('app.allow_user_lookup', true), '') = 'on'
            )
            WITH CHECK (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR NULLIF(current_setting('app.role', true), '') = 'super_admin'
            )
        ");
    }
};
