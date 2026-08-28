<?php

use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\TtxExercise;
use App\Models\TtxTeam;
use App\Models\User;
use App\Notifications\TrainingAssigned;

test('assigning training creates notification for the user', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create(['title' => 'Phishing 101', 'content' => 'x', 'duration_minutes' => 10]);

    $this->actingAs($admin)->post(route('tenant.assignments.store'), [
        'user_id' => $user->id,
        'training_module_id' => $module->id,
    ])->assertRedirect();

    // Assert sebagai user target (RLS: user hanya melihat miliknya)
    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/Notifications/Index')
            ->has('notifications', 1)
        );
});

test('adding ttx member creates notification', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $ex = TtxExercise::create(['tenant_id' => $tenant->id, 'title' => 'TTX Ransomware']);
    $team = TtxTeam::create(['tenant_id' => $tenant->id, 'exercise_id' => $ex->id, 'name' => 'Tim Recovery']);

    $this->actingAs($admin)->post(route('tenant.ttx.teams.members.store', $team), [
        'user_id' => $user->id,
        'role_in_team' => 'member',
    ])->assertRedirect();

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications', 1));
});

test('user sees own notifications and can mark all read', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create(['title' => 'M', 'content' => 'x', 'duration_minutes' => 5]);

    $user->notify(new TrainingAssigned($module));

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('User/Notifications/Index')->has('notifications', 1));

    $this->actingAs($user)->post(route('notifications.readAll'))->assertRedirect();

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications', 1));

    // Setelah read-all, unread count (bell) harus 0
    $this->actingAs($user)
        ->get(route('user.dashboard'))
        ->assertOk();

    expect(\App\Models\User::find($user->id)->unreadNotifications()->count())->toBe(0);
});

test('user cannot see another user notification', function () {
    $tenant = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenant->id]);
    $userB = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create(['title' => 'M', 'content' => 'x', 'duration_minutes' => 5]);

    $userB->notify(new TrainingAssigned($module));

    $this->actingAs($userA)->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications', 0));
});