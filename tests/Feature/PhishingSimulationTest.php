<?php

use App\Models\PhishingCampaign;
use App\Models\PhishingTarget;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
    Mail::fake();
});

test('tenant without phishing feature gets 403 on index', function () {
    $tenant = Tenant::factory()->create();
    
    // Starter Package (no phishing)
    $starter = Package::firstOrCreate(['slug' => 'starter'], [
        'name' => 'Starter',
        'price_monthly' => 500,
        'max_users' => 25,
        'features' => ['training'],
        'includes_all_modules' => false,
        'is_active' => true,
    ]);
    
    Subscription::create([
        'tenant_id' => $tenant->id,
        'package_id' => $starter->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    
    $response = $this->actingAs($admin)->get(route('tenant.phishing.index'));
    $response->assertStatus(403);
    $response->assertInertia(fn ($page) => $page
        ->component('Shared/FeatureLocked')
        ->has('title')
        ->where('message', 'Organisasi Anda belum mengaktifkan fitur Simulasi Phishing.')
    );
});

test('tenant with phishing feature can access index', function () {
    $tenant = Tenant::factory()->create();
    
    $pro = Package::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'features' => ['training', 'phishing'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    
    Subscription::create([
        'tenant_id' => $tenant->id,
        'package_id' => $pro->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    
    $response = $this->actingAs($admin)->get(route('tenant.phishing.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Phishing/Index')
        ->has('campaigns')
    );
});

test('admin can create campaign with targets', function () {
    $tenant = Tenant::factory()->create();
    
    $pro = Package::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'features' => ['training', 'phishing'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    
    Subscription::create([
        'tenant_id' => $tenant->id,
        'package_id' => $pro->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $user1 = User::factory()->create(['tenant_id' => $tenant->id]);
    $user2 = User::factory()->create(['tenant_id' => $tenant->id]);
    
    $response = $this->actingAs($admin)->post(route('tenant.phishing.store'), [
        'title' => 'Test Campaign',
        'sender_name' => 'IT Support',
        'subject' => 'Urgent: Verify Account',
        'body_template' => 'Click here: {{link}}',
        'target_user_ids' => [$user1->id, $user2->id],
    ]);
    
    $response->assertRedirect();
    
    $campaign = PhishingCampaign::where('title', 'Test Campaign')->first();
    expect($campaign)->not->toBeNull();
    expect($campaign->tenant_id)->toBe($tenant->id);
    expect($campaign->status)->toBe('draft');
    expect($campaign->targets)->toHaveCount(2);
    
    $tokens = $campaign->targets->pluck('token');
    expect($tokens->unique())->toHaveCount(2);
    expect($tokens->first())->toHaveLength(64);
});

test('campaign enforces RLS tenant isolation', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    $pro = Package::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'features' => ['training', 'phishing'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    
    Subscription::create(['tenant_id' => $tenant1->id, 'package_id' => $pro->id, 'status' => 'active', 'started_at' => now()]);
    Subscription::create(['tenant_id' => $tenant2->id, 'package_id' => $pro->id, 'status' => 'active', 'started_at' => now()]);
    
    $admin1 = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant1->id]);
    $admin2 = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant2->id]);
    
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
    DB::statement("SELECT set_config('app.tenant_id', '', false)");
    
    $campaign1 = PhishingCampaign::create([
        'tenant_id' => $tenant1->id,
        'title' => 'T1 Campaign',
        'sender_name' => 'IT',
        'subject' => 'Test',
        'body_template' => 'Link: {{link}}',
        'status' => 'draft',
        'created_by' => $admin1->id,
    ]);
    
    $campaign2 = PhishingCampaign::create([
        'tenant_id' => $tenant2->id,
        'title' => 'T2 Campaign',
        'sender_name' => 'IT',
        'subject' => 'Test',
        'body_template' => 'Link: {{link}}',
        'status' => 'draft',
        'created_by' => $admin2->id,
    ]);
    
    // Test via HTTP: admin1 cannot access campaign2
    $response = $this->actingAs($admin1)->get(route('tenant.phishing.show', $campaign2->id));
    $response->assertStatus(403);
    
    // Test via HTTP: admin1 can access campaign1
    $response = $this->actingAs($admin1)->get(route('tenant.phishing.show', $campaign1->id));
    $response->assertOk();
});

test('send campaign creates email snapshot and sends mail', function () {
    $tenant = Tenant::factory()->create();
    
    $pro = Package::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'features' => ['training', 'phishing'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    
    Subscription::create([
        'tenant_id' => $tenant->id,
        'package_id' => $pro->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
    $campaign = PhishingCampaign::create([
        'tenant_id' => $tenant->id,
        'title' => 'Send Test',
        'sender_name' => 'Security Team',
        'subject' => 'Action Required',
        'body_template' => 'Please verify: {{link}}',
        'status' => 'draft',
        'created_by' => $admin->id,
    ]);
    
    PhishingTarget::create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'token' => 'test-token-abc123',
        'status' => 'sent',
    ]);
    DB::statement("SELECT set_config('app.role', '', false)");
    
    $response = $this->actingAs($admin)->post(route('tenant.phishing.send', $campaign->id));
    $response->assertRedirect();
    
    $campaign->refresh();
    expect($campaign->status)->toBe('running');
    expect($campaign->email_snapshot)->not->toBeNull();
    expect($campaign->email_snapshot['subject'])->toBe('Action Required');
    expect($campaign->email_snapshot['body'])->toBe('Please verify: {{link}}');
    
    Mail::assertSent(\App\Mail\PhishingSimMail::class, 1);
});

test('clicking valid token marks target as clicked and shows teaching page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
    $campaign = PhishingCampaign::create([
        'tenant_id' => $tenant->id,
        'title' => 'Click Test',
        'sender_name' => 'IT',
        'subject' => 'Test',
        'body_template' => 'Link: {{link}}',
        'status' => 'running',
        'created_by' => $admin->id,
    ]);
    
    $target = PhishingTarget::create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'token' => 'valid-token-xyz',
        'status' => 'sent',
    ]);
    DB::statement("SELECT set_config('app.role', '', false)");
    
    $response = $this->get(route('phishing.trap', 'valid-token-xyz'));
    
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/PhishingTeaching')
        ->where('campaignTitle', 'Click Test')
    );
    
    $target->refresh();
    expect($target->status)->toBe('clicked');
    expect($target->clicked_at)->not->toBeNull();
});

test('clicking invalid token returns 404', function () {
    $response = $this->get(route('phishing.trap', 'invalid-token-123'));
    $response->assertStatus(404);
});

test('clicking already clicked token returns 404', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
    $campaign = PhishingCampaign::create([
        'tenant_id' => $tenant->id,
        'title' => 'Already Clicked',
        'sender_name' => 'IT',
        'subject' => 'Test',
        'body_template' => 'Link: {{link}}',
        'status' => 'running',
        'created_by' => $admin->id,
    ]);
    
    PhishingTarget::create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'token' => 'already-clicked-token',
        'status' => 'clicked',
        'clicked_at' => now()->subHour(),
    ]);
    DB::statement("SELECT set_config('app.role', '', false)");
    
    $response = $this->get(route('phishing.trap', 'already-clicked-token'));
    $response->assertStatus(404);
});

test('tenant cannot access another tenant campaign', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    $pro = Package::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'features' => ['training', 'phishing'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    
    Subscription::create(['tenant_id' => $tenant1->id, 'package_id' => $pro->id, 'status' => 'active', 'started_at' => now()]);
    Subscription::create(['tenant_id' => $tenant2->id, 'package_id' => $pro->id, 'status' => 'active', 'started_at' => now()]);
    
    $admin1 = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant1->id]);
    $admin2 = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant2->id]);
    
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
    $campaign = PhishingCampaign::create([
        'tenant_id' => $tenant2->id,
        'title' => 'T2 Private',
        'sender_name' => 'IT',
        'subject' => 'Test',
        'body_template' => 'Link: {{link}}',
        'status' => 'draft',
        'created_by' => $admin2->id,
    ]);
    DB::statement("SELECT set_config('app.role', '', false)");
    
    $response = $this->actingAs($admin1)->get(route('tenant.phishing.show', $campaign->id));
    $response->assertStatus(403);
});

test('teaching page shows no credential form', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
    $campaign = PhishingCampaign::create([
        'tenant_id' => $tenant->id,
        'title' => 'No Form Test',
        'sender_name' => 'IT',
        'subject' => 'Test',
        'body_template' => 'Link: {{link}}',
        'status' => 'running',
        'created_by' => $admin->id,
    ]);
    
    PhishingTarget::create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'token' => 'no-form-token',
        'status' => 'sent',
    ]);
    DB::statement("SELECT set_config('app.role', '', false)");
    
    $response = $this->get(route('phishing.trap', 'no-form-token'));
    
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/PhishingTeaching')
        ->where('campaignTitle', 'No Form Test')
    );
});
