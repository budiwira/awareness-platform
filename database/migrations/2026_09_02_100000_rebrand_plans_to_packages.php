<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rename tables
        Schema::rename('plans', 'packages');
        Schema::rename('plan_requests', 'package_requests');
        Schema::rename('plan_module', 'package_module');

        // 2. Rename columns
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->renameColumn('plan_id', 'package_id');
        });

        Schema::table('package_requests', function (Blueprint $table) {
            $table->renameColumn('plan_id', 'package_id');
        });

        Schema::table('package_module', function (Blueprint $table) {
            $table->renameColumn('plan_id', 'package_id');
        });

        // 3. Update packages table structure
        Schema::table('packages', function (Blueprint $table) {
            $table->integer('max_users')->nullable()->change();
            $table->boolean('is_free')->default(false)->after('price_monthly');
        });

        // 4. Update RLS Policies
        // packages (formerly plans)
        DB::statement('ALTER TABLE packages DISABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS plans_select_policy ON packages');
        DB::statement('DROP POLICY IF EXISTS plans_write_policy ON packages');
        DB::statement('ALTER TABLE packages ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY packages_select_policy ON packages FOR SELECT USING (true)');
        DB::statement("CREATE POLICY packages_write_policy ON packages FOR ALL WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");

        // package_requests (formerly plan_requests)
        DB::statement('ALTER TABLE package_requests DISABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS plan_requests_tenant_isolation ON package_requests');
        DB::statement('DROP POLICY IF EXISTS plan_requests_insert_policy ON package_requests');
        DB::statement('DROP POLICY IF EXISTS plan_requests_update_policy ON package_requests');
        DB::statement('ALTER TABLE package_requests ENABLE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY package_requests_tenant_isolation ON package_requests FOR SELECT USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
        DB::statement("CREATE POLICY package_requests_insert_policy ON package_requests FOR INSERT WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)");
        DB::statement("CREATE POLICY package_requests_update_policy ON package_requests FOR UPDATE USING (NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");

        // package_module (formerly plan_module)
        DB::statement('ALTER TABLE package_module DISABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS plan_module_select_policy ON package_module');
        DB::statement('DROP POLICY IF EXISTS plan_module_write_policy ON package_module');
        DB::statement('ALTER TABLE package_module ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY package_module_select_policy ON package_module FOR SELECT USING (true)');
        DB::statement("CREATE POLICY package_module_write_policy ON package_module FOR ALL WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        // Revert RLS Policies first
        DB::statement('DROP POLICY IF EXISTS package_module_select_policy ON package_module');
        DB::statement('DROP POLICY IF EXISTS package_module_write_policy ON package_module');
        DB::statement('DROP POLICY IF EXISTS package_requests_tenant_isolation ON package_requests');
        DB::statement('DROP POLICY IF EXISTS package_requests_insert_policy ON package_requests');
        DB::statement('DROP POLICY IF EXISTS package_requests_update_policy ON package_requests');
        DB::statement('DROP POLICY IF EXISTS packages_select_policy ON packages');
        DB::statement('DROP POLICY IF EXISTS packages_write_policy ON packages');

        // Restore old structure
        Schema::table('packages', function (Blueprint $table) {
            $table->integer('max_users')->default(5)->change();
            $table->dropColumn('is_free');
        });

        Schema::table('package_module', function (Blueprint $table) {
            $table->renameColumn('package_id', 'plan_id');
        });

        Schema::table('package_requests', function (Blueprint $table) {
            $table->renameColumn('package_id', 'plan_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->renameColumn('package_id', 'plan_id');
        });

        // Rename tables back
        Schema::rename('package_module', 'plan_module');
        Schema::rename('package_requests', 'plan_requests');
        Schema::rename('packages', 'plans');

        // Restore old RLS Policies
        DB::statement('ALTER TABLE plans ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY plans_select_policy ON plans FOR SELECT USING (true)');
        DB::statement("CREATE POLICY plans_write_policy ON plans FOR ALL WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");

        DB::statement('ALTER TABLE plan_requests ENABLE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY plan_requests_tenant_isolation ON plan_requests FOR SELECT USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
        DB::statement("CREATE POLICY plan_requests_insert_policy ON plan_requests FOR INSERT WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)");
        DB::statement("CREATE POLICY plan_requests_update_policy ON plan_requests FOR UPDATE USING (NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");

        DB::statement('ALTER TABLE plan_module ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY plan_module_select_policy ON plan_module FOR SELECT USING (true)');
        DB::statement("CREATE POLICY plan_module_write_policy ON plan_module FOR ALL WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }
};
