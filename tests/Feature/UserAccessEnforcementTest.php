<?php

use App\Models\Package;
use App\Models\ModuleAssignment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use App\Services\UserAccessManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant_admin']);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']);
    $this->module = TrainingModule::create([
        'title' => 'Test Module',
        'content' => 'Content',
        'duration_minutes' => 10,
        'is_active' => true,
        'status' => 'published',
    ]);
    $this->package = Package::create([
        'name' => 'Pro',
        'slug' => 'pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'includes_all_modules' => true,
        'features' => ['training', 'phishing'],
    ]);
    $this->subscription = Subscription::create([
        'tenant_id' => $this->tenant->id,
        'package_id' => $this->package->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    $this->assignment = ModuleAssignment::create([
        'user_id' => $this->user->id,
        'training_module_id' => $this->module->id,
        'tenant_id' => $this->tenant->id,
    ]);
});

test('revoked user blocked from training show (403)', function () {
    app(UserAccessManager::class)->revokeModuleAccess($this->user, $this->module, $this->admin);

    $response = $this->actingAs($this->user)->get(route('user.training.show', $this->assignment));

    $response->assertStatus(403);
});

test('non-revoked user can access training show (200)', function () {
    $response = $this->actingAs($this->user)->get(route('user.training.show', $this->assignment));

    $response->assertOk();
});

test('revoked module not shown in training index', function () {
    app(UserAccessManager::class)->revokeModuleAccess($this->user, $this->module, $this->admin);

    $response = $this->actingAs($this->user)->get(route('user.training.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('assignments', 0));
});

test('non-revoked module shown in training index', function () {
    $response = $this->actingAs($this->user)->get(route('user.training.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('assignments', 1));
});