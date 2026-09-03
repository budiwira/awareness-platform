<?php

use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserFeatureAccess;
use App\Models\CaseStudy;
use App\Models\CtfChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'tenant_admin']);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']);

    $this->package = Package::create([
        'name' => 'Pro',
        'slug' => 'pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'includes_all_modules' => true,
        'features' => ['case_studies', 'ctf'],
    ]);

    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'package_id' => $this->package->id,
        'status' => 'active',
        'started_at' => now(),
    ]);

    $this->caseStudy = CaseStudy::create([
        'title' => 'Test Case',
        'description' => 'Desc',
        'difficulty' => 'beginner',
        'duration_minutes' => 30,
        'status' => 'published',
        'is_active' => true,
    ]);

    $this->ctfChallenge = CtfChallenge::create([
        'title' => 'Test CTF',
        'description' => 'Desc',
        'category' => 'web',
        'difficulty' => 'easy',
        'points' => 100,
        'flag' => 'flag{test}',
        'hint' => 'Hint',
        'status' => 'published',
        'is_active' => true,
    ]);
});

test('revoked user blocked from cases index (403)', function () {
    app(\App\Services\UserAccessManager::class)->revokeFeatureAccess($this->user, 'case_studies', $this->admin);

    $response = $this->actingAs($this->user)->get(route('user.cases.index'));
    $response->assertStatus(403);
});

test('non-revoked user can access cases index', function () {
    $response = $this->actingAs($this->user)->get(route('user.cases.index'));
    $response->assertOk();
});

test('revoked user blocked from ctf index (403)', function () {
    app(\App\Services\UserAccessManager::class)->revokeFeatureAccess($this->user, 'ctf', $this->admin);

    $response = $this->actingAs($this->user)->get(route('user.ctf.index'));
    $response->assertStatus(403);
});

test('non-revoked user can access ctf index', function () {
    $response = $this->actingAs($this->user)->get(route('user.ctf.index'));
    $response->assertOk();
});