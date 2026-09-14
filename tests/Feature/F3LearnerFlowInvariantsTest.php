<?php

use App\Enums\UserRole;
use App\Models\ModuleAssignment;
use App\Models\Package;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;

function buildAssignmentWithQuizzes()
{
    $tenant = Tenant::factory()->create();
    $package = Package::create([
        'name' => 'T-'.uniqid(),
        'slug' => 't-'.uniqid(),
        'price_monthly' => 100,
        'max_users' => 100,
        'features' => ['training'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    Subscription::create([
        'tenant_id' => $tenant->id,
        'package_id' => $package->id,
        'status' => 'active',
        'started_at' => now(),
    ]);

    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::User]);
    $module = TrainingModule::create([
        'title' => 'Module '.uniqid(),
        'content' => 'x',
        'content_html' => '<p>x</p>',
        'duration_minutes' => 10,
        'status' => 'published',
        'is_active' => true,
    ]);

    $pretest = Quiz::create([
        'training_module_id' => $module->id,
        'title' => 'Pretest',
        'passing_score' => 50,
        'purpose' => 'pretest',
    ]);
    $posttest = Quiz::create([
        'training_module_id' => $module->id,
        'title' => 'Posttest',
        'passing_score' => 50,
        'purpose' => 'posttest',
    ]);

    QuizQuestion::create([
        'quiz_id' => $pretest->id,
        'question' => 'Q?',
        'options' => ['A', 'B'],
        'correct_index' => 0,
    ]);
    QuizQuestion::create([
        'quiz_id' => $posttest->id,
        'question' => 'Q?',
        'options' => ['A', 'B'],
        'correct_index' => 0,
    ]);

    $module->update([
        'pretest_quiz_id' => $pretest->id,
        'posttest_quiz_id' => $posttest->id,
    ]);

    $assignment = ModuleAssignment::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
        'score' => 0,
    ]);

    return [$user, $module, $pretest, $posttest, $assignment];
}

test('invariant F3: pretest attempt TIDAK update assignment.score', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $questionId = $pretest->questions->first()->id;

    $attempt = QuizAttempt::create([
        'quiz_id' => $pretest->id,
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'status' => 'in_progress',
        'started_at' => now(),
        'question_order' => [$questionId],
        'option_orders' => [$questionId => [0, 1]],
    ]);

    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attempt), [
            'answers' => [$questionId => 0],
        ])
        ->assertOk();

    $assignment->refresh();
    expect($assignment->score)->toBe(0)
        ->and($assignment->pretest_score)->toBe(100)
        ->and($assignment->status)->toBe('assigned');
});

test('invariant F3: pretest attempt TIDAK mark completed', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $questionId = $pretest->questions->first()->id;

    $attempt = QuizAttempt::create([
        'quiz_id' => $pretest->id,
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'status' => 'in_progress',
        'started_at' => now(),
        'question_order' => [$questionId],
        'option_orders' => [$questionId => [0, 1]],
    ]);

    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attempt), [
            'answers' => [$questionId => 0],
        ]);

    $assignment->refresh();
    expect($assignment->status)->toBe('assigned')
        ->and($assignment->completed_at)->toBeNull()
        ->and($assignment->pretest_completed_at)->not->toBeNull();
});

test('invariant F3: posttest attempt update assignment.score', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $questionId = $posttest->questions->first()->id;

    $attempt = QuizAttempt::create([
        'quiz_id' => $posttest->id,
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'status' => 'in_progress',
        'started_at' => now(),
        'question_order' => [$questionId],
        'option_orders' => [$questionId => [0, 1]],
    ]);

    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attempt), [
            'answers' => [$questionId => 0],
        ]);

    $assignment->refresh();
    expect($assignment->score)->toBe(100);
});

test('invariant F3: posttest passed mark completed', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $questionId = $posttest->questions->first()->id;

    $attempt = QuizAttempt::create([
        'quiz_id' => $posttest->id,
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'status' => 'in_progress',
        'started_at' => now(),
        'question_order' => [$questionId],
        'option_orders' => [$questionId => [0, 1]],
    ]);

    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attempt), [
            'answers' => [$questionId => 0],
        ]);

    $assignment->refresh();
    expect($assignment->status)->toBe('completed')
        ->and($assignment->completed_at)->not->toBeNull();
});

test('invariant F3: learning gain computed correctly', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $preQuestionId = $pretest->questions->first()->id;
    $postQuestionId = $posttest->questions->first()->id;

    // Submit pretest: score 0 (salah)
    $pre = QuizAttempt::create([
        'quiz_id' => $pretest->id,
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'status' => 'in_progress',
        'started_at' => now(),
        'question_order' => [$preQuestionId],
        'option_orders' => [$preQuestionId => [0, 1]],
    ]);
    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $pre), [
            'answers' => [$preQuestionId => 1], // salah
        ]);

    // Submit posttest: score 100 (benar)
    $post = QuizAttempt::create([
        'quiz_id' => $posttest->id,
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'status' => 'in_progress',
        'started_at' => now(),
        'question_order' => [$postQuestionId],
        'option_orders' => [$postQuestionId => [0, 1]],
    ]);
    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $post), [
            'answers' => [$postQuestionId => 0], // benar
        ]);

    $assignment->refresh();
    $learningGain = ($assignment->score ?? 0) - ($assignment->pretest_score ?? 0);
    expect($assignment->pretest_score)->toBe(0)
        ->and($assignment->score)->toBe(100)
        ->and($learningGain)->toBe(100);
});
