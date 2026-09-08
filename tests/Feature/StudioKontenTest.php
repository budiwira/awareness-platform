<?php

use App\Models\CaseStudy;
use App\Models\CtfChallenge;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;

test('super admin can create module with draft status', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)
        ->post(route('platform.modules.store'), [
            'title' => 'Password Security',
            'content' => 'Gunakan password kuat...',
            'duration_minutes' => 15,
            'status' => 'draft',
        ])
        ->assertRedirect();

    $module = TrainingModule::where('title', 'Password Security')->first();
    expect($module->status)->toBe('draft');
    $this->assertDatabaseHas('audit_logs', ['action' => 'module.created']);
});

test('super admin can publish a draft module', function () {
    $super = User::factory()->superAdmin()->create();
    $module = TrainingModule::create([
        'title' => 'Phishing 101',
        'content' => 'Materi...',
        'duration_minutes' => 10,
        'status' => 'draft',
    ]);

    $this->actingAs($super)
        ->post(route('platform.modules.publish', $module))
        ->assertRedirect();

    expect($module->fresh()->status)->toBe('published');
    $this->assertDatabaseHas('audit_logs', ['action' => 'module.published']);
});

test('super admin can archive a published module', function () {
    $super = User::factory()->superAdmin()->create();
    $module = TrainingModule::create([
        'title' => 'Phishing 101',
        'content' => 'Materi...',
        'duration_minutes' => 10,
        'status' => 'published',
    ]);

    $this->actingAs($super)
        ->post(route('platform.modules.archive', $module))
        ->assertRedirect();

    expect($module->fresh()->status)->toBe('archived');
    $this->assertDatabaseHas('audit_logs', ['action' => 'module.archived']);
});

test('draft modules are not visible to tenant admin in assignment dropdown', function () {
    $tenant = Tenant::factory()->create();
    $Package = Package::create(['name' => 'Dr-'.uniqid(), 'slug' => 'dr-'.uniqid(), 'price_monthly' => 100, 'max_users' => 100, 'features' => ['training'], 'includes_all_modules' => true, 'is_active' => true]);
    Subscription::create(['tenant_id' => $tenant->id, 'package_id' => $Package->id, 'status' => 'active', 'started_at' => now()]);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

    TrainingModule::create(['title' => 'Draft Module', 'content' => 'Test', 'duration_minutes' => 10, 'status' => 'draft', 'is_active' => true]);
    TrainingModule::create(['title' => 'Published Module', 'content' => 'Test', 'duration_minutes' => 10, 'status' => 'published', 'is_active' => true]);

    $response = $this->actingAs($admin)->get(route('tenant.assignments.index'));

    $modules = $response->viewData('page')['props']['modules'];
    expect($modules)->toHaveCount(1);
    expect($modules[0]['title'])->toBe('Published Module');
});

test('tenant admin cannot access platform modules routes', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->get(route('platform.modules.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('platform.modules.create'))->assertForbidden();
});

test('user only sees published ctf challenges', function () {
    $tenant = Tenant::factory()->create();
    $Package = Package::create(['name' => 'Cf-'.uniqid(), 'slug' => 'cf-'.uniqid(), 'price_monthly' => 100, 'max_users' => 100, 'features' => ['ctf'], 'includes_all_modules' => false, 'is_active' => true]);
    Subscription::create(['tenant_id' => $tenant->id, 'package_id' => $Package->id, 'status' => 'active', 'started_at' => now()]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    CtfChallenge::create([
        'title' => 'Draft Challenge',
        'category' => 'general',
        'difficulty' => 'beginner',
        'points' => 100,
        'flag' => 'FLAG{test}',
        'status' => 'draft',
        'is_active' => true,
    ]);

    CtfChallenge::create([
        'title' => 'Published Challenge',
        'category' => 'general',
        'difficulty' => 'beginner',
        'points' => 100,
        'flag' => 'FLAG{test2}',
        'status' => 'published',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get(route('user.ctf.index'));

    $challenges = $response->viewData('page')['props']['challenges'];
    expect($challenges)->toHaveCount(1);
    expect($challenges[0]['title'])->toBe('Published Challenge');
});

test('user only sees published case studies', function () {
    $tenant = Tenant::factory()->create();
    $Package = Package::create(['name' => 'Cs-'.uniqid(), 'slug' => 'cs-'.uniqid(), 'price_monthly' => 100, 'max_users' => 100, 'features' => ['case_studies'], 'includes_all_modules' => false, 'is_active' => true]);
    Subscription::create(['tenant_id' => $tenant->id, 'package_id' => $Package->id, 'status' => 'active', 'started_at' => now()]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    CaseStudy::create([
        'title' => 'Draft Case',
        'difficulty' => 'beginner',
        'duration_minutes' => 15,
        'status' => 'draft',
        'is_active' => true,
    ]);

    CaseStudy::create([
        'title' => 'Published Case',
        'difficulty' => 'beginner',
        'duration_minutes' => 15,
        'status' => 'published',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get(route('user.cases.index'));

    $cases = $response->viewData('page')['props']['cases'];
    expect($cases)->toHaveCount(1);
    expect($cases[0]['title'])->toBe('Published Case');
});

test('user cannot submit flag to draft ctf challenge', function () {
    $tenant = Tenant::factory()->create();
    $Package = Package::create(['name' => 'Dc-'.uniqid(), 'slug' => 'dc-'.uniqid(), 'price_monthly' => 100, 'max_users' => 100, 'features' => ['ctf'], 'includes_all_modules' => false, 'is_active' => true]);
    Subscription::create(['tenant_id' => $tenant->id, 'package_id' => $Package->id, 'status' => 'active', 'started_at' => now()]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $challenge = CtfChallenge::create([
        'title' => 'Draft Challenge',
        'category' => 'general',
        'difficulty' => 'beginner',
        'points' => 100,
        'flag' => 'FLAG{test}',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('user.ctf.submit', $challenge), ['flag' => 'FLAG{test}'])
        ->assertRedirect(route('user.ctf.index'));

    $this->assertDatabaseMissing('ctf_solves', ['user_id' => $user->id]);
});

test('user cannot start draft case study', function () {
    $tenant = Tenant::factory()->create();
    $Package = Package::create(['name' => 'Sd-'.uniqid(), 'slug' => 'sd-'.uniqid(), 'price_monthly' => 100, 'max_users' => 100, 'features' => ['case_studies'], 'includes_all_modules' => false, 'is_active' => true]);
    Subscription::create(['tenant_id' => $tenant->id, 'package_id' => $Package->id, 'status' => 'active', 'started_at' => now()]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $case = CaseStudy::create([
        'title' => 'Draft Case',
        'difficulty' => 'beginner',
        'duration_minutes' => 15,
        'status' => 'draft',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('user.cases.start', $case))
        ->assertRedirect(route('user.cases.index'));

    $this->assertDatabaseMissing('case_participations', ['user_id' => $user->id]);
});
