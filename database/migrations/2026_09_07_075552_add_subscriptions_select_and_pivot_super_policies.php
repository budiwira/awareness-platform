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
        $this->ddl()->statement("DROP POLICY IF EXISTS subscriptions_super_select ON subscriptions");
        $this->ddl()->statement("CREATE POLICY subscriptions_super_select ON subscriptions FOR SELECT USING (NULLIF(current_setting('app.role', true), '') = 'super_admin')");

        $this->ddl()->statement("DROP POLICY IF EXISTS package_module_super_delete ON package_module");
        $this->ddl()->statement("DROP POLICY IF EXISTS package_module_super_insert ON package_module");
        $this->ddl()->statement("CREATE POLICY package_module_super_delete ON package_module FOR DELETE USING (NULLIF(current_setting('app.role', true), '') = 'super_admin')");
        $this->ddl()->statement("CREATE POLICY package_module_super_insert ON package_module FOR INSERT WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        $this->ddl()->statement("DROP POLICY IF EXISTS subscriptions_super_select ON subscriptions");
        $this->ddl()->statement("DROP POLICY IF EXISTS package_module_super_delete ON package_module");
        $this->ddl()->statement("DROP POLICY IF EXISTS package_module_super_insert ON package_module");
    }
};