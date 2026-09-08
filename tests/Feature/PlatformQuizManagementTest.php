<?php

use App\Models\Quiz;
use App\Models\TrainingModule;
use App\Models\User;

test('super admin can create quiz for a module', function () {
    $super = User::factory()->superAdmin()->create();
    $module = TrainingModule::create(['title' => 'Phishing', 'content' => 'x', 'duration_minutes' => 10]);

    $this->actingAs($super)
        ->post(route('platform.quizzes.store'), [
            'training_module_id' => $module->id,
            'title' => 'Quiz Phishing',
            'passing_score' => 70,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('quizzes', ['title' => 'Quiz Phishing', 'training_module_id' => $module->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'quiz.created']);
});

test('super admin can add question with options', function () {
    $super = User::factory()->superAdmin()->create();
    $module = TrainingModule::create(['title' => 'Phishing', 'content' => 'x', 'duration_minutes' => 10]);
    $quiz = Quiz::create(['training_module_id' => $module->id, 'title' => 'Q', 'passing_score' => 70]);

    $this->actingAs($super)
        ->post(route('platform.quizzes.questions.store', $quiz), [
            'question' => 'Apa itu phishing?',
            'options' => ['Serangan sosial', 'Virus', 'Firewall', 'Backup'],
            'correct_index' => 0,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('quiz_questions', ['quiz_id' => $quiz->id, 'correct_index' => 0]);
});

test('correct index out of range is rejected', function () {
    $super = User::factory()->superAdmin()->create();
    $module = TrainingModule::create(['title' => 'Phishing', 'content' => 'x', 'duration_minutes' => 10]);
    $quiz = Quiz::create(['training_module_id' => $module->id, 'title' => 'Q', 'passing_score' => 70]);

    $this->actingAs($super)
        ->post(route('platform.quizzes.questions.store', $quiz), [
            'question' => 'Test',
            'options' => ['A', 'B'],
            'correct_index' => 5,
        ])
        ->assertSessionHasErrors('correct_index');
});

test('tenant admin cannot access quiz management', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->get(route('platform.quizzes.index'))->assertForbidden();
});
