<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertLegacyDataIsSafe();

        Schema::table('module_assignments', function (Blueprint $table) {
            $table->foreignId('assigned_by')->nullable()->after('training_module_id')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_by');
            $table->timestamp('deadline_at')->nullable()->after('assigned_at');
            $table->timestamp('started_at')->nullable()->after('deadline_at');
            $table->jsonb('module_snapshot')->nullable()->after('started_at');
            $table->foreignId('pretest_quiz_id')->nullable()->after('module_snapshot')->constrained('quizzes')->nullOnDelete();
            $table->foreignId('posttest_quiz_id')->nullable()->after('pretest_quiz_id')->constrained('quizzes')->nullOnDelete();
            $table->timestamp('content_started_at')->nullable()->after('pretest_completed_at');
            $table->timestamp('content_completed_at')->nullable()->after('content_started_at');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->foreignId('module_assignment_id')->nullable()->after('quiz_id')->constrained('module_assignments')->restrictOnDelete();
            $table->string('assessment_purpose', 20)->nullable()->after('module_assignment_id');
        });

        DB::statement(<<<'SQL'
            UPDATE module_assignments AS ma
            SET assigned_at = COALESCE(ma.assigned_at, ma.created_at),
                module_snapshot = jsonb_build_object(
                    'schema_version', 1,
                    'module_id', tm.id,
                    'title', tm.title,
                    'description', tm.description,
                    'content_html', tm.content_html,
                    'duration_minutes', tm.duration_minutes
                ),
                pretest_quiz_id = tm.pretest_quiz_id,
                posttest_quiz_id = tm.posttest_quiz_id
            FROM training_modules AS tm
            WHERE tm.id = ma.training_module_id
            SQL);

        // Preserve the legacy singular module.quiz fallback only when it is deterministic:
        // the module has exactly one quiz and that quiz is a posttest.
        DB::statement(<<<'SQL'
            UPDATE module_assignments AS ma
            SET posttest_quiz_id = legacy.quiz_id
            FROM (
                SELECT q.training_module_id, MIN(q.id) AS quiz_id
                FROM quizzes AS q
                GROUP BY q.training_module_id
                HAVING COUNT(*) = 1 AND BOOL_AND(q.purpose = 'posttest')
            ) AS legacy
            WHERE ma.training_module_id = legacy.training_module_id
              AND ma.pretest_quiz_id IS NULL
              AND ma.posttest_quiz_id IS NULL
            SQL);

        DB::statement(<<<'SQL'
            UPDATE quiz_attempts AS qa
            SET module_assignment_id = matched.assignment_id,
                assessment_purpose = matched.purpose
            FROM (
                SELECT qa2.id AS attempt_id, ma.id AS assignment_id, q.purpose
                FROM quiz_attempts AS qa2
                JOIN quizzes AS q ON q.id = qa2.quiz_id
                JOIN module_assignments AS ma
                  ON ma.user_id = qa2.user_id
                 AND ma.tenant_id = qa2.tenant_id
                 AND ma.training_module_id = q.training_module_id
            ) AS matched
            WHERE qa.id = matched.attempt_id
            SQL);

        $this->assertBackfillCompleted();

        DB::statement('ALTER TABLE module_assignments ALTER COLUMN assigned_at SET NOT NULL');
        DB::statement('ALTER TABLE module_assignments ALTER COLUMN module_snapshot SET NOT NULL');
        DB::statement('ALTER TABLE quiz_attempts ALTER COLUMN module_assignment_id SET NOT NULL');
        DB::statement('ALTER TABLE quiz_attempts ALTER COLUMN assessment_purpose SET NOT NULL');

        DB::statement(<<<'SQL'
            DO $$
            DECLARE constraint_name text;
            BEGIN
                SELECT c.conname INTO constraint_name
                FROM pg_constraint c
                WHERE c.conrelid = 'module_assignments'::regclass
                  AND c.contype = 'u'
                  AND (
                    SELECT array_agg(a.attname ORDER BY a.attname)
                    FROM unnest(c.conkey) AS key(attnum)
                    JOIN pg_attribute a
                      ON a.attrelid = c.conrelid AND a.attnum = key.attnum
                  ) = ARRAY['training_module_id', 'user_id']::name[];

                IF constraint_name IS NULL THEN
                    RAISE EXCEPTION 'Legacy module assignment unique constraint was not found';
                END IF;

                EXECUTE format('ALTER TABLE module_assignments DROP CONSTRAINT %I', constraint_name);
            END $$
            SQL);

        DB::statement('DROP INDEX IF EXISTS quizzes_one_pretest_per_module');
        DB::statement('DROP INDEX IF EXISTS quizzes_one_posttest_per_module');

        DB::statement("ALTER TABLE module_assignments ADD CONSTRAINT module_assignments_status_check CHECK (status IN ('assigned', 'in_progress', 'completed', 'cancelled'))");
        DB::statement("ALTER TABLE quiz_attempts ADD CONSTRAINT quiz_attempts_assessment_purpose_check CHECK (assessment_purpose IN ('pretest', 'posttest', 'practice'))");
        DB::statement("CREATE UNIQUE INDEX module_assignments_one_open_per_user_module ON module_assignments (user_id, training_module_id) WHERE status IN ('assigned', 'in_progress')");
        DB::statement("CREATE UNIQUE INDEX quiz_attempts_one_active_per_assignment_quiz ON quiz_attempts (module_assignment_id, quiz_id) WHERE status = 'in_progress'");
        DB::statement('CREATE INDEX quiz_attempts_assignment_status_index ON quiz_attempts (module_assignment_id, status)');
    }

    public function down(): void
    {
        $duplicateAssignments = DB::table('module_assignments')
            ->select('user_id', 'training_module_id')
            ->groupBy('user_id', 'training_module_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        $duplicatePurposes = DB::table('quizzes')
            ->select('training_module_id', 'purpose')
            ->whereIn('purpose', ['pretest', 'posttest'])
            ->groupBy('training_module_id', 'purpose')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateAssignments || $duplicatePurposes) {
            throw new RuntimeException('Cannot safely roll back lifecycle migration after multiple assignment cycles or assessment versions exist.');
        }

        DB::statement('DROP INDEX IF EXISTS quiz_attempts_assignment_status_index');
        DB::statement('DROP INDEX IF EXISTS quiz_attempts_one_active_per_assignment_quiz');
        DB::statement('DROP INDEX IF EXISTS module_assignments_one_open_per_user_module');
        DB::statement('ALTER TABLE quiz_attempts DROP CONSTRAINT IF EXISTS quiz_attempts_assessment_purpose_check');
        DB::statement('ALTER TABLE module_assignments DROP CONSTRAINT IF EXISTS module_assignments_status_check');
        DB::statement("CREATE UNIQUE INDEX quizzes_one_pretest_per_module ON quizzes (training_module_id) WHERE purpose = 'pretest'");
        DB::statement("CREATE UNIQUE INDEX quizzes_one_posttest_per_module ON quizzes (training_module_id) WHERE purpose = 'posttest'");
        DB::statement('ALTER TABLE module_assignments ADD CONSTRAINT module_assignments_user_id_training_module_id_unique UNIQUE (user_id, training_module_id)');

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_assignment_id');
            $table->dropColumn('assessment_purpose');
        });

        Schema::table('module_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropConstrainedForeignId('pretest_quiz_id');
            $table->dropConstrainedForeignId('posttest_quiz_id');
            $table->dropColumn([
                'assigned_at', 'deadline_at', 'started_at', 'module_snapshot',
                'content_started_at', 'content_completed_at', 'cancelled_at',
            ]);
        });
    }

    private function assertLegacyDataIsSafe(): void
    {
        $invalidBindings = DB::table('training_modules as tm')
            ->leftJoin('quizzes as pre', 'pre.id', '=', 'tm.pretest_quiz_id')
            ->leftJoin('quizzes as post', 'post.id', '=', 'tm.posttest_quiz_id')
            ->where(function ($query) {
                $query->where(function ($pre) {
                    $pre->whereNotNull('tm.pretest_quiz_id')
                        ->where(function ($invalid) {
                            $invalid->whereNull('pre.id')
                                ->orWhereColumn('pre.training_module_id', '<>', 'tm.id')
                                ->orWhere('pre.purpose', '<>', 'pretest');
                        });
                })->orWhere(function ($post) {
                    $post->whereNotNull('tm.posttest_quiz_id')
                        ->where(function ($invalid) {
                            $invalid->whereNull('post.id')
                                ->orWhereColumn('post.training_module_id', '<>', 'tm.id')
                                ->orWhere('post.purpose', '<>', 'posttest');
                        });
                });
            })
            ->count();

        $ambiguousAttempts = DB::table('quiz_attempts as qa')
            ->join('quizzes as q', 'q.id', '=', 'qa.quiz_id')
            ->leftJoin('module_assignments as ma', function ($join) {
                $join->on('ma.user_id', '=', 'qa.user_id')
                    ->on('ma.tenant_id', '=', 'qa.tenant_id')
                    ->on('ma.training_module_id', '=', 'q.training_module_id');
            })
            ->select('qa.id')
            ->groupBy('qa.id')
            ->havingRaw('COUNT(ma.id) <> 1')
            ->get()
            ->count();

        $duplicateActiveAttempts = DB::table('quiz_attempts as qa')
            ->join('quizzes as q', 'q.id', '=', 'qa.quiz_id')
            ->join('module_assignments as ma', function ($join) {
                $join->on('ma.user_id', '=', 'qa.user_id')
                    ->on('ma.tenant_id', '=', 'qa.tenant_id')
                    ->on('ma.training_module_id', '=', 'q.training_module_id');
            })
            ->where('qa.status', 'in_progress')
            ->select('ma.id', 'qa.quiz_id')
            ->groupBy('ma.id', 'qa.quiz_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($invalidBindings || $ambiguousAttempts || $duplicateActiveAttempts) {
            throw new RuntimeException("Unsafe assessment legacy data: invalid_bindings={$invalidBindings}, ambiguous_attempts={$ambiguousAttempts}, duplicate_active_attempts={$duplicateActiveAttempts}");
        }
    }

    private function assertBackfillCompleted(): void
    {
        $missingAssignments = DB::table('module_assignments')
            ->whereNull('assigned_at')
            ->orWhereNull('module_snapshot')
            ->count();
        $missingAttempts = DB::table('quiz_attempts')
            ->whereNull('module_assignment_id')
            ->orWhereNull('assessment_purpose')
            ->count();

        if ($missingAssignments || $missingAttempts) {
            throw new RuntimeException("Lifecycle backfill incomplete: assignments={$missingAssignments}, attempts={$missingAttempts}");
        }
    }
};
