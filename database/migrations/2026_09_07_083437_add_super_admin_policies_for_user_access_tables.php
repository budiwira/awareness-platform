<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function ddl()
    {
        $hasAdmin = (bool) config('database.connections.pgsql_admin.password');

        return DB::connection($hasAdmin ? 'pgsql_admin' : config('database.default'));
    }

    public function up(): void
    {
        // user_module_access - super admin policies
        $this->ddl()->statement('DROP POLICY IF EXISTS user_module_access_super_update ON user_module_access');
        $this->ddl()->statement('DROP POLICY IF EXISTS user_module_access_super_insert ON user_module_access');
        $this->ddl()->statement("CREATE POLICY user_module_access_super_update ON user_module_access FOR UPDATE USING (NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");
        $this->ddl()->statement("CREATE POLICY user_module_access_super_insert ON user_module_access FOR INSERT WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");

        // user_feature_access - super admin policies
        $this->ddl()->statement('DROP POLICY IF EXISTS user_feature_access_super_update ON user_feature_access');
        $this->ddl()->statement('DROP POLICY IF EXISTS user_feature_access_super_insert ON user_feature_access');
        $this->ddl()->statement("CREATE POLICY user_feature_access_super_update ON user_feature_access FOR UPDATE USING (NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");
        $this->ddl()->statement("CREATE POLICY user_feature_access_super_insert ON user_feature_access FOR INSERT WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        $this->ddl()->statement('DROP POLICY IF EXISTS user_module_access_super_update ON user_module_access');
        $this->ddl()->statement('DROP POLICY IF EXISTS user_module_access_super_insert ON user_module_access');
        $this->ddl()->statement('DROP POLICY IF EXISTS user_feature_access_super_update ON user_feature_access');
        $this->ddl()->statement('DROP POLICY IF EXISTS user_feature_access_super_insert ON user_feature_access');
    }
};
