<?php

use App\Models\ModuleAssignment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function makeQuizCbtFixture(): array
{
    $tenant = Tenant::factory()->create();
    $Package = \App\Models\Package::create(['name' => 'Quiz-'.uniqid(), 'slug' => 'quiz-'.uniqid(), 'price_monthly' => 100, 'max_users' => 100, 'features' => ['training'], 'includes_all_modules' => true, 'is_active' => true]);
    \App\Models\Subscription::create(['tenant_id' => $tenant->id, 'package_id' => $Package->id, 'status' => 'active', 'started_at' => now()]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create(['title' => 'Phishing', 'content' => 'x', 'duration_minutes' => 10, 'status' => 'published', 'is_active' => true]);
    $quiz = Quiz::create(['training_module_id' => $module->id, 'title' => 'Quiz P', 'passing_score' => 50, 'duration_minutes' => 30]);

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

test('start creates attempt with deadline and randomization', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizCbtFixture();

    $response = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $data = $response->json();

    expect($data)->toHaveKeys(['attempt_id', 'quiz', 'questions', 'deadline_at', 'started_at']);
    expect($data['questions'])->toHaveCount(2);
    expect($data['questions'][0])->not->toHaveKey('correct_index');

    $attempt = QuizAttempt::find($data['attempt_id']);
    expect($attempt->status)->toBe('in_progress');
    expect($attempt->deadline_at)->not->toBeNull();
    expect($attempt->question_order)->toHaveCount(2);
    expect($attempt->option_orders)->toHaveKeys([$q1->id, $q2->id]);
});

test('attempt payload does not contain correct_index', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizCbtFixture();

    $response = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $data = $response->json();

    foreach ($data['questions'] as $question) {
        expect($question)->not->toHaveKey('correct_index');
    }
});

test('submit before deadline creates submitted attempt with correct score', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizCbtFixture();

    $startResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $attemptId = $startResponse->json('attempt_id');
    $attempt = QuizAttempt::find($attemptId);

    // Map jawaban ke posisi teracak
    $answers = [];
    $optionOrderQ1 = $attempt->option_orders[$q1->id];
    $optionOrderQ2 = $attempt->option_orders[$q2->id];

    // Cari posisi shuffled yang mengarah ke correct_index
    $shuffledIndexQ1 = array_search(0, $optionOrderQ1); // correct_index q1 = 0
    $shuffledIndexQ2 = array_search(1, $optionOrderQ2); // correct_index q2 = 1

    $answers[$q1->id] = $shuffledIndexQ1;
    $answers[$q2->id] = $shuffledIndexQ2;

    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attemptId), ['answers' => $answers])
        ->assertOk()
        ->assertJson(['score' => 100, 'passed' => true, 'status' => 'submitted']);

    $this->assertDatabaseHas('quiz_attempts', [
        'id' => $attemptId,
        'status' => 'submitted',
        'score' => 100,
        'passed' => true,
    ]);

    $this->assertDatabaseHas('module_assignments', [
        'id' => $assignment->id,
        'status' => 'completed',
        'score' => 100,
    ]);
});

test('submit after deadline creates expired attempt', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizCbtFixture();

    $startResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $attemptId = $startResponse->json('attempt_id');
    $attempt = QuizAttempt::find($attemptId);

    // Paksa deadline lewat
    $attempt->update(['deadline_at' => now()->subMinutes(5)]);

    $answers = [$q1->id => 0, $q2->id => 1];

    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attemptId), ['answers' => $answers])
        ->assertOk()
        ->assertJson(['status' => 'expired']);

    $this->assertDatabaseHas('quiz_attempts', [
        'id' => $attemptId,
        'status' => 'expired',
    ]);
});

test('expired in_progress attempt is finalized on next start', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizCbtFixture();

    // Buat attempt pertama
    $firstResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $firstAttemptId = $firstResponse->json('attempt_id');
    $firstAttempt = QuizAttempt::find($firstAttemptId);

    // Paksa deadline lewat
    $firstAttempt->update(['deadline_at' => now()->subMinutes(5)]);

    // Start lagi
    $secondResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    // Attempt pertama harus expired
    $firstAttempt->refresh();
    expect($firstAttempt->status)->toBe('expired');

    // Attempt kedua harus baru
    $secondAttemptId = $secondResponse->json('attempt_id');
    expect($secondAttemptId)->not->toBe($firstAttemptId);

    $secondAttempt = QuizAttempt::find($secondAttemptId);
    expect($secondAttempt->status)->toBe('in_progress');
});

test('cannot start quiz after passing', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizCbtFixture();

    // Start dan lulus
    $startResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $attemptId = $startResponse->json('attempt_id');
    $attempt = QuizAttempt::find($attemptId);

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
        ->assertJson(['passed' => true]);

    // Coba start lagi
    $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertStatus(422)
        ->assertJson(['message' => 'Anda sudah lulus quiz ini.']);
});

test('user cannot access another user attempt', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizCbtFixture();
    $other = User::factory()->create(['tenant_id' => $user->tenant_id]);

    $startResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $attemptId = $startResponse->json('attempt_id');

    $this->actingAs($other)
        ->getJson(route('user.training.quiz.attempt', $attemptId))
        ->assertForbidden();

    $this->actingAs($other)
        ->postJson(route('user.training.quiz.submit', $attemptId), ['answers' => []])
        ->assertForbidden();
});

test('continuing in_progress attempt returns same attempt', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizCbtFixture();

    $firstResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $firstAttemptId = $firstResponse->json('attempt_id');

    // Start lagi tanpa submit
    $secondResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $secondAttemptId = $secondResponse->json('attempt_id');

    expect($secondAttemptId)->toBe($firstAttemptId);
});

test('quiz show page indicates already passed', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizCbtFixture();

    // Buat attempt lulus
    $startResponse = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $attemptId = $startResponse->json('attempt_id');
    $attempt = QuizAttempt::find($attemptId);

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
        ->assertOk();

    // Akses halaman quiz
    $this->actingAs($user)
        ->get(route('user.training.quiz', $assignment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/MyTraining/Quiz')
            ->where('alreadyPassed', true)
            ->has('passedAttempt')
        );
});
