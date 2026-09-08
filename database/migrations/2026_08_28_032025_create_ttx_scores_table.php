<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ttx_scores', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('exercise_id')->constrained('ttx_exercises')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('score');
            $table->timestamps();
            $table->unique(['user_id', 'exercise_id']);
        });

        DB::statement('ALTER TABLE ttx_scores ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS ttx_scores_tenant_isolation ON ttx_scores');
        DB::statement("CREATE POLICY ttx_scores_tenant_isolation ON ttx_scores FOR ALL USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        Schema::dropIfExists('ttx_scores');
    }
};
