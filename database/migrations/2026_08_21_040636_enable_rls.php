<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('GRANT USAGE ON SCHEMA public TO awareness_app');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO awareness_app');
        DB::statement('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO awareness_app');
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO awareness_app');
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO awareness_app');

        DB::statement('ALTER TABLE tenants ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE users ENABLE ROW LEVEL SECURITY');

        // Idempotent: bersihkan policy lama bila ada, baru buat
        DB::statement('DROP POLICY IF EXISTS users_tenant_isolation ON users');
        DB::statement('DROP POLICY IF EXISTS tenants_tenant_isolation ON tenants');

        DB::statement("
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

        DB::statement("
            CREATE POLICY tenants_tenant_isolation ON tenants
            FOR ALL
            USING (
                id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR NULLIF(current_setting('app.role', true), '') = 'super_admin'
            )
            WITH CHECK (
                id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR NULLIF(current_setting('app.role', true), '') = 'super_admin'
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS users_tenant_isolation ON users');
        DB::statement('DROP POLICY IF EXISTS tenants_tenant_isolation ON tenants');
        DB::statement('ALTER TABLE users DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenants DISABLE ROW LEVEL SECURITY');
    }
};
