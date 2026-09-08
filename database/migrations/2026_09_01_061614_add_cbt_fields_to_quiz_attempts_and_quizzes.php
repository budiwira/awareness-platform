<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE quizzes DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE quiz_attempts DISABLE ROW LEVEL SECURITY');

        Schema::table('quizzes', function (Blueprint $table) {
            $table->integer('duration_minutes')->nullable()->after('passing_score');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->string('status', 20)->default('in_progress')->after('tenant_id');
            $table->timestamp('started_at')->nullable()->after('status');
            $table->timestamp('deadline_at')->nullable()->after('started_at');
            $table->timestamp('submitted_at')->nullable()->after('deadline_at');
            $table->jsonb('question_order')->nullable()->after('answers');
            $table->jsonb('option_orders')->nullable()->after('question_order');

            $table->integer('score')->nullable()->change();
            $table->boolean('passed')->nullable()->change();
        });

        DB::statement('ALTER TABLE quizzes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE quiz_attempts ENABLE ROW LEVEL SECURITY');
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn(['status', 'started_at', 'deadline_at', 'submitted_at', 'question_order', 'option_orders']);
            $table->integer('score')->nullable(false)->change();
            $table->boolean('passed')->nullable(false)->change();
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};
