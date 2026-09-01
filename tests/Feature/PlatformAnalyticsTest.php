<?php

use App\Models\ModuleAssignment;
use App\Models\PhishingCampaign;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
});

test('platform dashboard shows aggregate summary', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    
    // Create multiple tenants
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    User::factory()->count(3)->create(['tenant_id' => $tenant1->id]);
    User::factory()->count(2)->create(['tenant_id' => $tenant2->id]);
    
    $response = $this->actingAs($superAdmin)->get(route('platform.reports'));
    
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Platform/Dashboard')
        ->has('summary')
        ->has('summary.total_tenants')
        ->has('summary.active_tenants')
        ->has('summary.total_users')
        ->has('summary.avg_platform_awareness_score')
    );
});

test('platform dashboard shows top tenants by risk', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    
    $tenant1 = Tenant::factory()->create(['name' => 'High Risk Corp']);
    $tenant2 = Tenant::factory()->create(['name' => 'Low Risk Inc']);
    
    $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
    $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
    
    $response = $this->actingAs($superAdmin)->get(route('platform.reports'));
    
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Platform/Dashboard')
        ->has('top_tenants_by_risk')
    );
});

test('platform dashboard shows plan distribution', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    
    $proPlan = Plan::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'features' => ['training', 'reports_export'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    
    $starterPlan = Plan::firstOrCreate(['slug' => 'starter'], [
        'name' => 'Starter',
        'price_monthly' => 0,
        'max_users' => 10,
        'features' => ['training'],
        'includes_all_modules' => false,
        'is_active' => true,
    ]);
    
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    $tenant1->subscriptions()->update(['status' => 'ended', 'ends_at' => now()]);
    Subscription::create([
        'tenant_id' => $tenant1->id,
        'plan_id' => $proPlan->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    
    $tenant2->subscriptions()->update(['status' => 'ended', 'ends_at' => now()]);
    Subscription::create([
        'tenant_id' => $tenant2->id,
        'plan_id' => $starterPlan->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    
    $response = $this->actingAs($superAdmin)->get(route('platform.reports'));
    
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Platform/Dashboard')
        ->has('plan_distribution')
    );
});

test('platform dashboard shows phishing adoption metrics', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    
    $proPlan = Plan::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'features' => ['training', 'phishing'],
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
    
    PhishingCampaign::create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Campaign',
        'sender_name' => 'Security',
        'subject' => 'Test',
        'body_template' => 'Test',
        'status' => 'sent',
        'created_by' => $admin->id,
    ]);
    
    $response = $this->actingAs($superAdmin)->get(route('platform.reports'));
    
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Platform/Dashboard')
        ->has('phishing_adoption')
        ->has('phishing_adoption.tenants_with_phishing_feature')
        ->has('phishing_adoption.tenants_actively_sending')
        ->has('phishing_adoption.total_campaigns_sent')
    );
});

test('platform analytics query is optimized', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    
    // Create many tenants
    for ($i = 0; $i < 10; $i++) {
        $tenant = Tenant::factory()->create();
        User::factory()->count(5)->create(['tenant_id' => $tenant->id]);
    }
    
    DB::enableQueryLog();
    
    $response = $this->actingAs($superAdmin)->get(route('platform.reports'));
    
    $queryCount = count(DB::getQueryLog());
    
    // Should be reasonable number of queries (aggregate queries expected)
    expect($queryCount)->toBeLessThan(100);
    
    $response->assertOk();
});

test('regular tenant admin cannot access platform analytics', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    
    $response = $this->actingAs($admin)->get(route('platform.reports'));
    
    $response->assertStatus(403);
});
