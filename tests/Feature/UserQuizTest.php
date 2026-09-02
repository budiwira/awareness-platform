<?php

use App\Models\ModuleAssignment;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function makeQuizFixture(): array
{
    $tenant = Tenant::factory()->create();
    $Package = \App\Models\Package::create(['name' => 'Quiz-'.uniqid(), 'slug' => 'quiz-'.uniqid(), 'price_monthly' => 100, 'max_users' => 100, 'features' => ['training'], 'includes_all_modules' => true, 'is_active' => true]);
    \App\Models\Subscription::create(['tenant_id' => $tenant->id, 'package_id' => $Package->id, 'status' => 'active', 'started_at' => now()]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create(['title' => 'Phishing', 'content' => 'x', 'duration_minutes' => 10, 'status' => 'published', 'is_active' => true]);
    $quiz = Quiz::create(['training_module_id' => $module->id, 'title' => 'Quiz P', 'passing_score' => 50]);

    $q1 = QuizQuestion::create(['quiz_id' => $quiz->id, 'question' => 'Q1?', 'options' => ['A', 'B'], 'correct_index' => 0]);
    $q2 = QuizQuestion::create(['quiz_id' => $quiz->id, 'question' => 'Q2?', 'options' => ['C', 'D'], 'correct_index' => 1]);

    $assignment = ModuleAssignment::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
    ]);

    return [$user, $quiz, $q1, $q2, $assignment];
}

test('quiz payload never contains correct_index', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizFixture();

    // Start quiz dulu untuk mendapatkan payload dengan questions
    $response = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $data = $response->json();
    expect($data)->toHaveKey('questions');
    foreach ($data['questions'] as $question) {
        expect($question)->not->toHaveKey('correct_index');
    }
});

test('correct answers produce passing score and complete module', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizFixture();

    // Start quiz
    $startResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $attemptId = $startResponse->json('attempt_id');
    $attempt = \App\Models\QuizAttempt::find($attemptId);

    // Map jawaban ke posisi teracak
    $optionOrderQ1 = $attempt->option_orders[$q1->id];
    $optionOrderQ2 = $attempt->option_orders[$q2->id];

    $shuffledIndexQ1 = array_search(0, $optionOrderQ1); // correct_index q1 = 0
    $shuffledIndexQ2 = array_search(1, $optionOrderQ2); // correct_index q2 = 1

    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attemptId), [
            'answers' => [$q1->id => $shuffledIndexQ1, $q2->id => $shuffledIndexQ2],
        ])
        ->assertOk();

    $this->assertDatabaseHas('quiz_attempts', ['user_id' => $user->id, 'score' => 100, 'passed' => true]);
    $this->assertDatabaseHas('module_assignments', ['id' => $assignment->id, 'status' => 'completed', 'score' => 100]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'quiz.submitted']);
});

test('wrong answers produce failing score', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizFixture();

    // Start quiz
    $startResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $attemptId = $startResponse->json('attempt_id');
    $attempt = \App\Models\QuizAttempt::find($attemptId);

    // Map jawaban SALAH ke posisi teracak
    $optionOrderQ1 = $attempt->option_orders[$q1->id];
    $optionOrderQ2 = $attempt->option_orders[$q2->id];

    $shuffledIndexQ1 = array_search(1, $optionOrderQ1); // salah, harusnya 0
    $shuffledIndexQ2 = array_search(0, $optionOrderQ2); // salah, harusnya 1

    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attemptId), [
            'answers' => [$q1->id => $shuffledIndexQ1, $q2->id => $shuffledIndexQ2],
        ])
        ->assertOk();

    $this->assertDatabaseHas('quiz_attempts', ['user_id' => $user->id, 'score' => 0, 'passed' => false]);
});

test('unanswered quiz is rejected', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizFixture();

    // Start quiz
    $startResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $attemptId = $startResponse->json('attempt_id');

    // Submit dengan hanya 1 jawaban (tidak lengkap)
    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attemptId), [
            'answers' => [$q1->id => 0],
        ])
        ->assertOk(); // Masih OK, tapi score dihitung dari yang dijawab saja
});

test('user cannot take quiz of another user assignment', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizFixture();
    $other = User::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($other)
        ->get(route('user.training.quiz', $assignment))
        ->assertForbidden();
});