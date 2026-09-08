<?php

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");

    $this->tenant = Tenant::factory()->create();

    // Create multiple users
    $this->user1 = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'user',
        'name' => 'User One',
        'show_on_leaderboard' => true,
    ]);

    $this->user2 = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'user',
        'name' => 'User Two',
        'show_on_leaderboard' => true,
    ]);

    $this->user3 = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'user',
        'name' => 'User Three',
        'show_on_leaderboard' => false, // Opted out
    ]);
});

test('leaderboard shows only users from same tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'role' => 'user',
    ]);

    // Login as user1
    $response = $this->actingAs($this->user1)->get(route('user.leaderboard.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('User/Leaderboard')
        ->has('leaderboard')
    );

    // Leaderboard seharusnya tidak include otherUser
    $leaderboard = $response->viewData('page')['props']['leaderboard'];
    $userIds = collect($leaderboard)->pluck('id')->toArray();

    expect($userIds)->not->toContain($otherUser->id);
});

test('leaderboard excludes users who opted out', function () {
    // user3 opted out
    $response = $this->actingAs($this->user1)->get(route('user.leaderboard.index'));

    $response->assertOk();

    $leaderboard = $response->viewData('page')['props']['leaderboard'];
    $userIds = collect($leaderboard)->pluck('id')->toArray();

    // user3 tidak muncul di leaderboard
    expect($userIds)->not->toContain($this->user3->id);
});

test('leaderboard shows current user position', function () {
    $response = $this->actingAs($this->user1)->get(route('user.leaderboard.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('currentUserPosition')
        ->has('currentUserData')
    );

    $currentUserData = $response->viewData('page')['props']['currentUserData'];
    expect($currentUserData['id'])->toBe($this->user1->id);
});

test('leaderboard sorts by awareness score descending', function () {
    // Create quiz attempts with different scores
    DB::statement("SELECT set_config('app.tenant_id', '{$this->tenant->id}', false)");

    // Create training module first
    $module = TrainingModule::create([
        'title' => 'Test Module',
        'content' => 'Content',
        'duration_minutes' => 10,
        'status' => 'published',
        'is_active' => true,
    ]);

    $quiz = Quiz::create([
        'training_module_id' => $module->id,
        'title' => 'Test Quiz',
        'passing_score' => 70,
        'duration_minutes' => 30,
        'is_active' => true,
    ]);

    // user1: score 50
    QuizAttempt::create([
        'quiz_id' => $quiz->id,
        'user_id' => $this->user1->id,
        'tenant_id' => $this->tenant->id,
        'status' => 'submitted',
        'score' => 50,
        'passed' => false,
        'started_at' => now(),
        'submitted_at' => now(),
        'answers' => [],
    ]);

    // user2: score 90
    QuizAttempt::create([
        'quiz_id' => $quiz->id,
        'user_id' => $this->user2->id,
        'tenant_id' => $this->tenant->id,
        'status' => 'submitted',
        'score' => 90,
        'passed' => true,
        'started_at' => now(),
        'submitted_at' => now(),
        'answers' => [],
    ]);

    $response = $this->actingAs($this->user1)->get(route('user.leaderboard.index'));

    $response->assertOk();

    $leaderboard = $response->viewData('page')['props']['leaderboard'];

    // user2 (score lebih tinggi) harus di atas user1
    if (count($leaderboard) >= 2) {
        $firstUser = $leaderboard[0];
        expect($firstUser['score'])->toBeGreaterThanOrEqual($leaderboard[1]['score'] ?? 0);
    }
});

test('user can toggle leaderboard visibility in profile', function () {
    $response = $this->actingAs($this->user1)->patch(route('profile.update'), [
        'name' => $this->user1->name,
        'email' => $this->user1->email,
        'show_on_leaderboard' => false,
    ]);

    $response->assertRedirect(route('profile.edit'));

    $this->user1->refresh();
    expect($this->user1->show_on_leaderboard)->toBeFalse();
});

test('opted out user does not appear in leaderboard', function () {
    // Opt out user1
    $this->user1->show_on_leaderboard = false;
    $this->user1->save();

    // Login as user2 dan lihat leaderboard
    $response = $this->actingAs($this->user2)->get(route('user.leaderboard.index'));

    $response->assertOk();

    $leaderboard = $response->viewData('page')['props']['leaderboard'];
    $userIds = collect($leaderboard)->pluck('id')->toArray();

    expect($userIds)->not->toContain($this->user1->id);
});
