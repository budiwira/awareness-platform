<?php

use App\Models\TrainingModule;
use App\Models\User;

test('super admin can list training modules', function () {
    $super = User::factory()->superAdmin()->create();
    TrainingModule::create(['title' => 'Phishing 101', 'content' => 'Materi...', 'duration_minutes' => 10]);

    $this->actingAs($super)
        ->get(route('platform.modules.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Platform/TrainingModules/Index'));
});

test('super admin can create a training module', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)
        ->post(route('platform.modules.store'), [
            'title' => 'Password Security',
            'content' => 'Gunakan password kuat...',
            'duration_minutes' => 15,
        ])
        ->assertRedirect(route('platform.modules.index'));

    $this->assertDatabaseHas('training_modules', ['title' => 'Password Security']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'module.created']);
});

test('tenant admin cannot access platform modules', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->get(route('platform.modules.index'))->assertForbidden();
});