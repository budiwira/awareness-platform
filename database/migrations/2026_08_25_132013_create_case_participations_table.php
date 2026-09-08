<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('tenant_id')->index();
            $table->foreignId('case_study_id')->constrained('case_studies')->cascadeOnDelete();
            $table->string('status', 20)->default('in_progress');
            $table->integer('score')->nullable();
            $table->jsonb('decisions')->nullable(); // { scene_id: chosen_option_index }
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'case_study_id']);
        });

        DB::statement('ALTER TABLE case_participations ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS case_participations_tenant_isolation ON case_participations');
        DB::statement("CREATE POLICY case_participations_tenant_isolation ON case_participations FOR ALL USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR user_id = NULLIF(current_setting('app.user_id', true), '')::bigint OR NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        Schema::dropIfExists('case_participations');
    }
};
