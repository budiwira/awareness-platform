<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->text('question');
            $table->jsonb('options');
            $table->integer('correct_index');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE quiz_questions ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS quiz_questions_select_policy ON quiz_questions');
        DB::statement("CREATE POLICY quiz_questions_select_policy ON quiz_questions FOR SELECT USING (true)");
        DB::statement('DROP POLICY IF EXISTS quiz_questions_write_policy ON quiz_questions');
        DB::statement("CREATE POLICY quiz_questions_write_policy ON quiz_questions FOR ALL WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};