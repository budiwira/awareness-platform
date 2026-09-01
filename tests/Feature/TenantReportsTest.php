<?php

use App\Models\ModuleAssignment;
use App\Models\PhishingCampaign;
use App\Models\PhishingTarget;
use App\Models\Plan;
use App\Models\QuizAttempt;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
});

test('tenant reports index shows summary and user list', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    
    // Create module + assignments
    $module = TrainingModule::create([
        'title' => 'Security 101',
        'content' => 'test',
        'duration_minutes' => 30,
        'status' => 'published',
        'is_active' => true,
    ]);
    
    $user1 = User::factory()->create(['tenant_id' => $tenant->id]);
    ModuleAssignment::create([
        'user_id' => $user1->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module->id,
        'status' => 'completed',
        'score' => 85,
        'completed_at' => now(),
    ]);
    
    $response = $this->actingAs($admin)->get(route('tenant.reports'));
    
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Reports/Index')
        ->has('summary')
        ->has('trend')
        ->has('risk_tiers')
        ->has('users')
        ->where('can_export', true) // Pro plan default
    );
});

test('tenant reports CSV export works for Pro plan', function () {
    $proPlan = Plan::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'features' => ['training', 'reports_export'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    
    $tenant = Tenant::factory()->create();
    $tenant->subscriptions()->update(['status' => 'ended', 'ends_at' => now()]);
    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $proPlan->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Test User', 'email' => 'test@example.com']);
    
    $response = $this->actingAs($admin)->get(route('tenant.reports.export'));
    
    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->getContent())->toContain('user_name,email,awareness_score');
    expect($response->getContent())->toContain('Test User,test@example.com');
});

test('tenant reports CSV export shows locked page for Starter plan', function () {
    $starterPlan = Plan::firstOrCreate(['slug' => 'starter'], [
        'name' => 'Starter',
        'price_monthly' => 0,
        'max_users' => 10,
        'features' => ['training'], // no reports_export
        'includes_all_modules' => false,
        'is_active' => true,
    ]);
    
    $tenant = Tenant::factory()->create();
    $tenant->subscriptions()->update(['status' => 'ended', 'ends_at' => now()]);
    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $starterPlan->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    
    $response = $this->actingAs($admin)->get(route('tenant.reports.export'));
    
    $response->assertInertia(fn ($page) => $page
        ->component('Shared/FeatureLocked')
        ->where('feature', 'Export Reports')
    );
});

test('tenant admin cannot view user detail from another tenant', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    $admin1 = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant1->id]);
    $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
    
    $response = $this->actingAs($admin1)->get(route('tenant.reports.users.show', $user2->id));
    
    $response->assertStatus(403);
});

test('tenant admin can view user detail from own tenant', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    
    $response = $this->actingAs($admin)->get(route('tenant.reports.users.show', $user->id));
    
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/UserDetailReport')
        ->has('report')
        ->has('report.user')
        ->has('report.summary')
        ->has('report.assignments')
        ->has('report.quiz_attempts')
        ->has('report.phishing_history')
    );
});

test('tenant reports include phishing data in user list', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    
    // Create phishing campaign + target
    $campaign = PhishingCampaign::create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Campaign',
        'sender_name' => 'Security Team',
        'subject' => 'Urgent',
        'body_template' => 'Click here',
        'status' => 'sent',
        'created_by' => $admin->id,
    ]);
    
    PhishingTarget::create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'token' => 'test-token',
        'status' => 'sent',
        'clicked_at' => now(),
    ]);
    
    $response = $this->actingAs($admin)->get(route('tenant.reports'));
    
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Reports/Index')
        ->has('users')
    );
});

test('CSV export does not include sensitive fields', function () {
    $proPlan = Plan::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'features' => ['training', 'reports_export'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    
    $tenant = Tenant::factory()->create();
    $tenant->subscriptions()->update(['status' => 'ended', 'ends_at' => now()]);
    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $proPlan->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'password' => bcrypt('secret123'),
    ]);
    
    $response = $this->actingAs($admin)->get(route('tenant.reports.export'));
    
    $csv = $response->getContent();
    expect($csv)->not->toContain('secret123');
    expect($csv)->toContain('user_name,email,awareness_score');
});
