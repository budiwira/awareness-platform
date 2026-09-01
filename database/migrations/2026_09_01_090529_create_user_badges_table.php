<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('badge_id')->constrained()->onDelete('cascade');
            $table->uuid('tenant_id')->index();
            $table->timestamp('earned_at');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['user_id', 'badge_id']); // user hanya bisa earn badge sekali
        });

        // Enable RLS untuk user_badges
        DB::statement('ALTER TABLE user_badges ENABLE ROW LEVEL SECURITY');

        DB::statement('DROP POLICY IF EXISTS user_badges_tenant_isolation ON user_badges');
        DB::statement("
            CREATE POLICY user_badges_tenant_isolation ON user_badges
            FOR SELECT
            USING (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR NULLIF(current_setting('app.role', true), '') = 'super_admin'
            )
        ");

        DB::statement('DROP POLICY IF EXISTS user_badges_insert_policy ON user_badges');
        DB::statement("
            CREATE POLICY user_badges_insert_policy ON user_badges
            FOR INSERT
            WITH CHECK (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
            )
        ");

        DB::statement('DROP POLICY IF EXISTS user_badges_update_policy ON user_badges');
        DB::statement("
            CREATE POLICY user_badges_update_policy ON user_badges
            FOR UPDATE
            USING (
                NULLIF(current_setting('app.role', true), '') = 'super_admin'
            )
            WITH CHECK (
                NULLIF(current_setting('app.role', true), '') = 'super_admin'
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
    }
};
