<?php

use App\Models\ModuleAssignment;
use App\Models\Package;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;

/**
 * Regression tests untuk B33: null/missing answers dalam array harus dihitung sebagai salah,
 * bukan benar (karena (int)null === 0 di PHP).
 * 
 * Catatan: Empty array [] dan null ditolak di validation layer (422), bukan di scoring layer.
 * 
 * @see docs/qa/BUG-LOG.md B33
 */

function makeB33QuizFixture(): array
{
    $tenant = Tenant::factory()->create();
    $Package = Package::create([
        'name' => 'B33-'.uniqid(),
        'slug' => 'b33-'.uniqid(),
        'price_monthly' => 100,
        'max_users' => 100,
        'features' => ['training'],
        'includes_all_modules' => true,
        'is_active' => true
    ]);
    Subscription::create([
        'tenant_id' => $tenant->id,
        'package_id' => $Package->id,
        'status' => 'active',
        'started_at' => now()
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create([
        'title' => 'B33 Test',
        'content' => 'x',
        'duration_minutes' => 10,
        'status' => 'published',
        'is_active' => true
    ]);
    $quiz = Quiz::create([
        'training_module_id' => $module->id,
        'title' => 'Quiz B33',
        'passing_score' => 50,
        'duration_minutes' => 30
    ]);

    // Q1: correct_index = 0
    $q1 = QuizQuestion::create([
        'quiz_id' => $quiz->id,
        'question' => 'Q1?',
        'options' => ['A', 'B', 'C', 'D'],
        'correct_index' => 0
    ]);
    // Q2: correct_index = 1
    $q2 = QuizQuestion::create([
        'quiz_id' => $quiz->id,
        'question' => 'Q2?',
        'options' => ['A', 'B', 'C', 'D'],
        'correct_index' => 1
    ]);

    $assignment = ModuleAssignment::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
    ]);

    return [$user, $quiz, $q1, $q2, $assignment];
}

test('B33 regression: empty answers array rejected at validation layer (422)', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeB33QuizFixture();

    $startResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $attemptId = $startResponse->json('attempt_id');

    // Submit dengan answers array KOSONG ? ditolak validation (422)
    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attemptId), ['answers' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('answers');

    // Attempt masih in_progress, tidak terupdate
    $this->assertDatabaseHas('quiz_attempts', [
        'id' => $attemptId,
        'status' => 'in_progress',
    ]);
});

test('B33 regression: null answers rejected at validation layer (422)', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeB33QuizFixture();

    $startResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $attemptId = $startResponse->json('attempt_id');

    // Submit dengan answers = null ? ditolak validation (422)
    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attemptId), ['answers' => null])
        ->assertStatus(422)
        ->assertJsonValidationErrors('answers');
});

test('B33 regression: partial answers calculates score correctly', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeB33QuizFixture();

    $startResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $attemptId = $startResponse->json('attempt_id');
    $attempt = QuizAttempt::find($attemptId);

    // Hanya jawab Q1 dengan benar, Q2 tidak dijawab (missing dari array)
    $optionOrderQ1 = $attempt->option_orders[$q1->id];
    $shuffledIndexQ1 = array_search(0, $optionOrderQ1); // correct_index q1 = 0

    $answers = [
        $q1->id => $shuffledIndexQ1,
        // Q2 sengaja tidak ada di answers array (missing key)
    ];

    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attemptId), ['answers' => $answers])
        ->assertOk()
        ->assertJson(['score' => 50, 'passed' => true]); // 1/2 = 50%

    $this->assertDatabaseHas('quiz_attempts', [
        'id' => $attemptId,
        'score' => 50,
    ]);
});

test('B33 regression: answer with null value counts as wrong', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeB33QuizFixture();

    $startResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $attemptId = $startResponse->json('attempt_id');
    $attempt = QuizAttempt::find($attemptId);

    // Q1 jawab benar, Q2 jawab null (explicit null value)
    $optionOrderQ1 = $attempt->option_orders[$q1->id];
    $shuffledIndexQ1 = array_search(0, $optionOrderQ1);

    $answers = [
        $q1->id => $shuffledIndexQ1,
        $q2->id => null, // explicit null
    ];

    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attemptId), ['answers' => $answers])
        ->assertOk()
        ->assertJson(['score' => 50, 'passed' => true]);

    $this->assertDatabaseHas('quiz_attempts', [
        'id' => $attemptId,
        'score' => 50,
    ]);
});

test('B33 regression: all correct answers still produce score 100', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeB33QuizFixture();

    $startResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $attemptId = $startResponse->json('attempt_id');
    $attempt = QuizAttempt::find($attemptId);

    // Jawab semua benar
    $optionOrderQ1 = $attempt->option_orders[$q1->id];
    $optionOrderQ2 = $attempt->option_orders[$q2->id];

    $shuffledIndexQ1 = array_search(0, $optionOrderQ1);
    $shuffledIndexQ2 = array_search(1, $optionOrderQ2);

    $answers = [
        $q1->id => $shuffledIndexQ1,
        $q2->id => $shuffledIndexQ2,
    ];

    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attemptId), ['answers' => $answers])
        ->assertOk()
        ->assertJson(['score' => 100, 'passed' => true, 'status' => 'submitted']);

    $this->assertDatabaseHas('quiz_attempts', [
        'id' => $attemptId,
        'score' => 100,
        'passed' => true,
    ]);
});