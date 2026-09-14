<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_modules', function (Blueprint $table) {
            $table->foreignId('pretest_quiz_id')->nullable()->after('content_html')->constrained('quizzes')->nullOnDelete();
            $table->foreignId('posttest_quiz_id')->nullable()->after('pretest_quiz_id')->constrained('quizzes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('training_modules', function (Blueprint $table) {
            $table->dropForeign(['pretest_quiz_id']);
            $table->dropForeign(['posttest_quiz_id']);
            $table->dropColumn(['pretest_quiz_id', 'posttest_quiz_id']);
        });
    }
};
