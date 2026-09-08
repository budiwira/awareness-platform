<?php

use App\Models\ModuleAssignment;
use App\Models\Package;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use App\Services\UserAccessManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 1. Setup Tenant & Package
    $this->tenant = Tenant::factory()->create();
    $this->package = Package::create(['name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 1500, 'max_users' => 100, 'includes_all_modules' => false, 'features' => ['training']]);
    $this->subscription = Subscription::create([
        'started_at' => now(),
        'tenant_id' => $this->tenant->id,
        'package_id' => $this->package->id,
        'status' => 'active',
    ]);

    // 2. Setup Module
    $this->module = TrainingModule::create([
        'title' => 'Modul Test',
        'content' => 'Konten',
        'duration_minutes' => 10,
        'is_active' => true,
        'status' => 'published',
    ]);
    $this->package->modules()->attach($this->module->id);

    // 3. Setup Users
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant_admin']);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']);

    // 4. Setup Assignment & Quiz
    $this->assignment = ModuleAssignment::create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'training_module_id' => $this->module->id,
        'status' => 'assigned',
    ]);

    $this->quiz = Quiz::create([
        'training_module_id' => $this->module->id,
        'title' => 'Test Quiz',
        'passing_score' => 70,
        'duration_minutes' => 10,
        'is_active' => true,
    ]);

    // Minimal 1 question (start() checks questions->count() > 0)
    QuizQuestion::create([
        'quiz_id' => $this->quiz->id,
        'question' => 'Test question?',
        'options' => ['A', 'B', 'C', 'D'],
        'correct_index' => 0,
    ]);
});

test('normal user can start quiz for assigned module', function () {
    $this->actingAs($this->user)
        ->postJson(route('user.training.quiz.start', $this->assignment->id))
        ->assertOk(); // Atau assertJsonStructure sesuai payload Anda
});

test('revoked user is blocked from starting quiz with 403 Forbidden', function () {
    // Revoke akses modul untuk user ini
    $manager = app(UserAccessManager::class);
    $manager->revokeModuleAccess($this->user, $this->module, $this->admin);

    // Coba akses endpoint start
    $this->actingAs($this->user)
        ->postJson(route('user.training.quiz.start', $this->assignment->id))
        ->assertForbidden()
        ->assertJson(['message' => 'Akses modul dibatasi oleh admin untuk user ini.']);
});
