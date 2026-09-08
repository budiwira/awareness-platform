<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_feature_access');
        Schema::create('user_feature_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('feature_key', 100);
            $table->uuid('tenant_id');
            $table->boolean('is_allowed')->default(true);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'feature_key']);
            $table->index('tenant_id');
        });

        Schema::table('user_feature_access', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        DB::statement('ALTER TABLE user_feature_access ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS user_feature_access_tenant_isolation ON user_feature_access');
        DB::statement("CREATE POLICY user_feature_access_tenant_isolation ON user_feature_access FOR SELECT USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
        DB::statement('DROP POLICY IF EXISTS user_feature_access_insert_policy ON user_feature_access');
        DB::statement("CREATE POLICY user_feature_access_insert_policy ON user_feature_access FOR INSERT WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
        DB::statement('DROP POLICY IF EXISTS user_feature_access_update_policy ON user_feature_access');
        DB::statement("CREATE POLICY user_feature_access_update_policy ON user_feature_access FOR UPDATE USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
        DB::statement('DROP POLICY IF EXISTS user_feature_access_delete_policy ON user_feature_access');
        DB::statement("CREATE POLICY user_feature_access_delete_policy ON user_feature_access FOR DELETE USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");

        $appUser = config('database.connections.pgsql.username');
        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON user_feature_access TO \"$appUser\"");
        DB::statement("GRANT USAGE, SELECT ON SEQUENCE user_feature_access_id_seq TO \"$appUser\"");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_feature_access');
    }
};
