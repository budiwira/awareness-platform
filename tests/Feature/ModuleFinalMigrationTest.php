<?php

use App\Models\Quiz;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function moduleFinalMigration(): object
{
    return require database_path('migrations/2026_09_29_000000_finalize_module_assignment_lifecycle.php');
}

test('lifecycle migration backfills legacy assignment and attempt deterministically', function () {
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
    $tenant = Tenant::factory()->create();
    $learner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
    $module = TrainingModule::create([
        'title' => 'Legacy module',
        'description' => 'Historical description',
        'content_html' => '<p>Historical content</p>',
        'duration_minutes' => 25,
        'status' => 'published',
        'is_active' => true,
    ]);
    $pretest = Quiz::create([
        'training_module_id' => $module->id,
        'title' => 'Legacy pretest',
        'purpose' => 'pretest',
        'passing_score' => 70,
    ]);
    $module->update(['pretest_quiz_id' => $pretest->id]);
    $migration = moduleFinalMigration();
    $migration->down();
    $createdAt = now()->subYear()->startOfSecond();
    $assignmentId = DB::table('module_assignments')->insertGetId([
        'user_id' => $learner->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
    $attemptId = DB::table('quiz_attempts')->insertGetId([
        'quiz_id' => $pretest->id,
        'user_id' => $learner->id,
        'tenant_id' => $tenant->id,
        'status' => 'submitted',
        'started_at' => $createdAt,
        'submitted_at' => $createdAt,
        'score' => 40,
        'passed' => false,
        'answers' => json_encode([]),
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    $migration->up();

    $assignment = DB::table('module_assignments')->where('id', $assignmentId)->first();
    $attempt = DB::table('quiz_attempts')->where('id', $attemptId)->first();
    $snapshot = json_decode($assignment->module_snapshot, true);

    expect($assignment->assigned_at)->toBe($createdAt->format('Y-m-d H:i:s'))
        ->and($assignment->pretest_quiz_id)->toBe($pretest->id)
        ->and($assignment->posttest_quiz_id)->toBeNull()
        ->and($snapshot)->toMatchArray([
            'schema_version' => 1,
            'module_id' => $module->id,
            'title' => 'Legacy module',
            'content_html' => '<p>Historical content</p>',
        ])
        ->and($attempt->module_assignment_id)->toBe($assignmentId)
        ->and($attempt->assessment_purpose)->toBe('pretest');
});

test('lifecycle migration refuses ambiguous legacy attempt ownership', function () {
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
    $tenant = Tenant::factory()->create();
    $learner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
    $module = TrainingModule::create([
        'title' => 'Ambiguous module',
        'content' => 'content',
        'duration_minutes' => 10,
    ]);
    $quiz = Quiz::create([
        'training_module_id' => $module->id,
        'title' => 'Legacy posttest',
        'purpose' => 'posttest',
        'passing_score' => 70,
    ]);
    $migration = moduleFinalMigration();
    $migration->down();
    DB::statement('ALTER TABLE module_assignments DROP CONSTRAINT module_assignments_user_id_training_module_id_unique');
    $rows = [
        [
            'user_id' => $learner->id,
            'tenant_id' => $tenant->id,
            'training_module_id' => $module->id,
            'status' => 'completed',
            'created_at' => now()->subYear(),
            'updated_at' => now()->subYear(),
        ],
        [
            'user_id' => $learner->id,
            'tenant_id' => $tenant->id,
            'training_module_id' => $module->id,
            'status' => 'assigned',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ];
    DB::table('module_assignments')->insert($rows);
    DB::table('quiz_attempts')->insert([
        'quiz_id' => $quiz->id,
        'user_id' => $learner->id,
        'tenant_id' => $tenant->id,
        'status' => 'submitted',
        'score' => 60,
        'passed' => false,
        'answers' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'ambiguous_attempts=1');
});

test('lifecycle migration reports invalid bindings zero-match attempts and duplicate active attempts', function () {
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
    $tenant = Tenant::factory()->create();
    $learner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
    $module = TrainingModule::create(['title' => 'Legacy blockers', 'content' => 'content', 'duration_minutes' => 10]);
    $otherModule = TrainingModule::create(['title' => 'Other', 'content' => 'content', 'duration_minutes' => 10]);
    $quiz = Quiz::create([
        'training_module_id' => $module->id, 'title' => 'Legacy pretest',
        'purpose' => 'pretest', 'passing_score' => 70,
    ]);
    $wrongQuiz = Quiz::create([
        'training_module_id' => $otherModule->id, 'title' => 'Wrong pretest',
        'purpose' => 'pretest', 'passing_score' => 70,
    ]);
    $migration = moduleFinalMigration();
    $migration->down();

    DB::table('training_modules')->where('id', $module->id)->update(['pretest_quiz_id' => $wrongQuiz->id]);
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'invalid_bindings=1');
    DB::table('training_modules')->where('id', $module->id)->update(['pretest_quiz_id' => null]);

    $attempt = [
        'quiz_id' => $quiz->id, 'user_id' => $learner->id, 'tenant_id' => $tenant->id,
        'status' => 'in_progress', 'score' => null, 'passed' => null,
        'created_at' => now(), 'updated_at' => now(),
    ];
    $orphanAttemptId = DB::table('quiz_attempts')->insertGetId($attempt);
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'ambiguous_attempts=1');
    DB::table('quiz_attempts')->where('id', $orphanAttemptId)->delete();

    DB::table('module_assignments')->insert([
        'user_id' => $learner->id, 'tenant_id' => $tenant->id,
        'training_module_id' => $module->id, 'status' => 'assigned',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('quiz_attempts')->insert([$attempt, $attempt]);

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'duplicate_active_attempts=1');
});
