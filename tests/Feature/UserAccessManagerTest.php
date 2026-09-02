<?php

use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use App\Models\UserFeatureAccess;
use App\Models\UserModuleAccess;
use App\Services\UserAccessManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->module = TrainingModule::create(['title' => 'Modul Test', 'content' => 'Konten', 'duration_minutes' => 10, 'is_active' => true, 'status' => 'published']);
    $this->package = Package::create([
        'name' => 'Pro',
        'slug' => 'pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'includes_all_modules' => true, 'features' => ['training', 'reports_export', 'ttx', 'case_studies', 'phishing'],
    ]);
    $this->subscription = Subscription::create([
        'tenant_id' => $this->tenant->id,
        'package_id' => $this->package->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    $this->manager = app(UserAccessManager::class);
});

test('default allow tanpa record override', function () {
    expect($this->manager->hasModuleAccess($this->user, $this->module))->toBeTrue();
    expect($this->manager->hasFeatureAccess($this->user, 'phishing'))->toBeTrue();
});

test('explicit deny memblokir akses module', function () {
    $this->manager->revokeModuleAccess($this->user, $this->module, $this->actor);
    expect($this->manager->hasModuleAccess($this->user, $this->module))->toBeFalse();
});

test('grant setelah deny memulihkan akses', function () {
    $this->manager->revokeModuleAccess($this->user, $this->module, $this->actor);
    $this->manager->grantModuleAccess($this->user, $this->module, $this->actor);
    expect($this->manager->hasModuleAccess($this->user, $this->module))->toBeTrue();
});

test('modul di luar package return false walau ada override allow', function () {
    $this->package->update(['includes_all_modules' => false]);
    $this->manager->grantModuleAccess($this->user, $this->module, $this->actor);
    expect($this->manager->hasModuleAccess($this->user, $this->module))->toBeFalse();
});

test('feature di luar package return false', function () {
    $this->package->update(['features' => ['training']]);
    expect($this->manager->hasFeatureAccess($this->user, 'phishing'))->toBeFalse();
});

test('grant/revoke menulis audit_logs', function () {
    $this->manager->grantModuleAccess($this->user, $this->module, $this->actor);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.module_access_granted',
        'subject_id' => $this->module->id,
    ]);

    $this->manager->revokeModuleAccess($this->user, $this->module, $this->actor);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.module_access_revoked',
        'subject_id' => $this->module->id,
    ]);
});

test('grant dua kali tidak duplikat row (updateOrCreate)', function () {
    $this->manager->grantModuleAccess($this->user, $this->module, $this->actor);
    $this->manager->grantModuleAccess($this->user, $this->module, $this->actor);
    expect(UserModuleAccess::where('user_id', $this->user->id)->count())->toBe(1);
});

test('tenant tanpa subscription return false', function () {
    $tenant2 = Tenant::factory()->create();
    $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
    expect($this->manager->hasModuleAccess($user2, $this->module))->toBeFalse();
});