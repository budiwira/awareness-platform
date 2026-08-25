<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_scenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_study_id')->constrained('case_studies')->cascadeOnDelete();
            $table->integer('order')->default(1);
            $table->text('situation');
            // options: [{ "text": "...", "quality": "best|acceptable|poor", "feedback": "..." }]
            $table->jsonb('options');
            $table->timestamps();

            $table->index(['case_study_id', 'order']);
        });

        DB::statement('ALTER TABLE case_scenes ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS case_scenes_select_policy ON case_scenes');
        DB::statement("CREATE POLICY case_scenes_select_policy ON case_scenes FOR SELECT USING (true)");
        DB::statement('DROP POLICY IF EXISTS case_scenes_write_policy ON case_scenes');
        DB::statement("CREATE POLICY case_scenes_write_policy ON case_scenes FOR ALL WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        Schema::dropIfExists('case_scenes');
    }
};