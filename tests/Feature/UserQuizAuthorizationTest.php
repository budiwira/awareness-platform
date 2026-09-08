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

test('user without assignment cannot start quiz', function () {
    $tenant = Tenant::factory()->create();
    $Package = Package::create(['name' => 'Test-'.uniqid(), 'slug' => 'test-'.uniqid(), 'price_monthly' => 100, 'max_users' => 100, 'features' => ['training'], 'includes_all_modules' => true, 'is_active' => true]);
    Subscription::create(['tenant_id' => $tenant->id, 'package_id' => $Package->id, 'status' => 'active', 'started_at' => now()]);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $otherUser = User::factory()->create(['tenant_id' => $tenant->id]);

    $module = TrainingModule::create(['title' => 'Phishing', 'content' => 'x', 'duration_minutes' => 10, 'status' => 'published', 'is_active' => true]);
    $quiz = Quiz::create(['training_module_id' => $module->id, 'title' => 'Quiz P', 'passing_score' => 50, 'duration_minutes' => 30]);

    QuizQuestion::create(['quiz_id' => $quiz->id, 'question' => 'Q1?', 'options' => ['A', 'B'], 'correct_index' => 0]);

    // Assignment untuk user lain
    $assignment = ModuleAssignment::create([
        'user_id' => $otherUser->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
    ]);

    // User tanpa assignment coba akses
    $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertForbidden();
});

test('user with assignment but without entitlement cannot start quiz', function () {
    $tenant = Tenant::factory()->create();
    // Package tanpa includes_all_modules
    $Package = Package::create(['name' => 'Limited-'.uniqid(), 'slug' => 'limited-'.uniqid(), 'price_monthly' => 50, 'max_users' => 50, 'features' => ['training'], 'includes_all_modules' => false, 'module_ids' => [], 'is_active' => true]);
    Subscription::create(['tenant_id' => $tenant->id, 'package_id' => $Package->id, 'status' => 'active', 'started_at' => now()]);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $module = TrainingModule::create(['title' => 'Phishing', 'content' => 'x', 'duration_minutes' => 10, 'status' => 'published', 'is_active' => true]);
    $quiz = Quiz::create(['training_module_id' => $module->id, 'title' => 'Quiz P', 'passing_score' => 50, 'duration_minutes' => 30]);

    QuizQuestion::create(['quiz_id' => $quiz->id, 'question' => 'Q1?', 'options' => ['A', 'B'], 'correct_index' => 0]);

    $assignment = ModuleAssignment::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
    ]);

    // User dengan assignment tapi tenant tidak entitled
    $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertStatus(403)
        ->assertJson(['message' => 'Organisasi Anda belum mengaktifkan modul ini.']);
});

test('user with assignment and entitlement can start quiz', function () {
    $tenant = Tenant::factory()->create();
    $Package = Package::create(['name' => 'Full-'.uniqid(), 'slug' => 'full-'.uniqid(), 'price_monthly' => 100, 'max_users' => 100, 'features' => ['training'], 'includes_all_modules' => true, 'is_active' => true]);
    Subscription::create(['tenant_id' => $tenant->id, 'package_id' => $Package->id, 'status' => 'active', 'started_at' => now()]);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $module = TrainingModule::create(['title' => 'Phishing', 'content' => 'x', 'duration_minutes' => 10, 'status' => 'published', 'is_active' => true]);
    $quiz = Quiz::create(['training_module_id' => $module->id, 'title' => 'Quiz P', 'passing_score' => 50, 'duration_minutes' => 30]);

    QuizQuestion::create(['quiz_id' => $quiz->id, 'question' => 'Q1?', 'options' => ['A', 'B'], 'correct_index' => 0]);
    QuizQuestion::create(['quiz_id' => $quiz->id, 'question' => 'Q2?', 'options' => ['C', 'D'], 'correct_index' => 1]);

    $assignment = ModuleAssignment::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
    ]);

    // Happy path
    $response = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $data = $response->json();
    expect($data)->toHaveKeys(['attempt_id', 'quiz', 'questions']);

    $attempt = QuizAttempt::find($data['attempt_id']);
    expect($attempt->user_id)->toBe($user->id);
    expect($attempt->quiz_id)->toBe($quiz->id);
    expect($attempt->tenant_id)->toBe($tenant->id);
});

test('attempt created is tied to correct user and assignment context', function () {
    $tenant = Tenant::factory()->create();
    $Package = Package::create(['name' => 'Quiz-'.uniqid(), 'slug' => 'quiz-'.uniqid(), 'price_monthly' => 100, 'max_users' => 100, 'features' => ['training'], 'includes_all_modules' => true, 'is_active' => true]);
    Subscription::create(['tenant_id' => $tenant->id, 'package_id' => $Package->id, 'status' => 'active', 'started_at' => now()]);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $module = TrainingModule::create(['title' => 'Phishing', 'content' => 'x', 'duration_minutes' => 10, 'status' => 'published', 'is_active' => true]);
    $quiz = Quiz::create(['training_module_id' => $module->id, 'title' => 'Quiz P', 'passing_score' => 50, 'duration_minutes' => 30]);

    QuizQuestion::create(['quiz_id' => $quiz->id, 'question' => 'Q1?', 'options' => ['A', 'B'], 'correct_index' => 0]);

    $assignment = ModuleAssignment::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', $assignment))
        ->assertOk();

    $attemptId = $response->json('attempt_id');
    $attempt = QuizAttempt::find($attemptId);

    // Verifikasi attempt terikat ke user dan quiz yang benar
    expect($attempt->user_id)->toBe($user->id);
    expect($attempt->tenant_id)->toBe($tenant->id);
    expect($attempt->quiz_id)->toBe($quiz->id);

    // Verifikasi quiz terkait dengan module yang di-assign
    expect($quiz->training_module_id)->toBe($assignment->training_module_id);
});
