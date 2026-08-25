<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_module_id')->constrained('training_modules')->cascadeOnDelete();
            $table->string('title', 255);
            $table->integer('passing_score')->default(70);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE quizzes ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS quizzes_select_policy ON quizzes');
        DB::statement("CREATE POLICY quizzes_select_policy ON quizzes FOR SELECT USING (is_active = true OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
        DB::statement('DROP POLICY IF EXISTS quizzes_write_policy ON quizzes');
        DB::statement("CREATE POLICY quizzes_write_policy ON quizzes FOR ALL WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};