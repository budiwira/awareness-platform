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
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create(['title' => 'Phishing', 'content' => 'x', 'duration_minutes' => 10]);
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

    $this->actingAs($user)
        ->get(route('user.training.quiz', $assignment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/MyTraining/Quiz')
            ->has('questions', 2, fn (Assert $q) => $q->missing('correct_index')->etc())
        );
});

test('correct answers produce passing score and complete module', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizFixture();

    $this->actingAs($user)
        ->post(route('user.training.quiz.submit', $assignment), [
            'answers' => [$q1->id => 0, $q2->id => 1],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('quiz_attempts', ['user_id' => $user->id, 'score' => 100, 'passed' => true]);
    $this->assertDatabaseHas('module_assignments', ['id' => $assignment->id, 'status' => 'completed', 'score' => 100]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'quiz.submitted']);
});

test('wrong answers produce failing score', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizFixture();

    $this->actingAs($user)
        ->post(route('user.training.quiz.submit', $assignment), [
            'answers' => [$q1->id => 1, $q2->id => 0],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('quiz_attempts', ['user_id' => $user->id, 'score' => 0, 'passed' => false]);
});

test('unanswered quiz is rejected', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizFixture();

    $this->actingAs($user)
        ->post(route('user.training.quiz.submit', $assignment), [
            'answers' => [$q1->id => 0],
        ])
        ->assertSessionHasErrors('answers');
});

test('user cannot take quiz of another user assignment', function () {
    [$user, $quiz, $q1, $q2, $assignment] = makeQuizFixture();
    $other = User::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($other)
        ->get(route('user.training.quiz', $assignment))
        ->assertForbidden();
});