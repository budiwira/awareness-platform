<?php

use App\Models\ModuleAssignment;
use App\Models\PhishingCampaign;
use App\Models\PhishingTarget;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
});

test('klik phishing trap membuat assignment remedial jika modul tersedia', function () {
    $tenant = Tenant::factory()->create();
    
    $pro = Plan::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'features' => ['training', 'phishing'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    
    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $pro->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    
    // Buat modul remedial
    $remedialModule = TrainingModule::create([
        'title' => 'Keamanan Email Dasar',
        'content' => 'Cara mengenali phishing',
        'duration_minutes' => 30,
        'status' => 'published',
        'is_active' => true,
    ]);
    
    $campaign = PhishingCampaign::create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Campaign',
        'sender_name' => 'IT',
        'subject' => 'Test',
        'body_template' => 'Link: {{link}}',
        'status' => 'sent',
        'created_by' => $user->id,
    ]);
    
    $token = bin2hex(random_bytes(32));
    PhishingTarget::create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'token' => $token,
        'status' => 'sent',
    ]);
    
    // User klik trap
    $response = $this->get(route('phishing.trap', $token));
    $response->assertOk();
    
    // Cek assignment terbuat
    $assignment = ModuleAssignment::where('user_id', $user->id)
        ->where('training_module_id', $remedialModule->id)
        ->where('tenant_id', $tenant->id)
        ->first();
    
    expect($assignment)->not->toBeNull();
    expect($assignment->status)->toBe('assigned');
});

test('klik phishing trap tidak membuat assignment duplikat', function () {
    $tenant = Tenant::factory()->create();
    
    $pro = Plan::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'features' => ['training', 'phishing'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    
    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $pro->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    
    $remedialModule = TrainingModule::create([
        'title' => 'Phishing Awareness',
        'content' => 'Cara mengenali phishing',
        'duration_minutes' => 30,
        'status' => 'published',
        'is_active' => true,
    ]);
    
    // Assignment sudah ada
    ModuleAssignment::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $remedialModule->id,
        'status' => 'in_progress',
    ]);
    
    $campaign = PhishingCampaign::create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Campaign',
        'sender_name' => 'IT',
        'subject' => 'Test',
        'body_template' => 'Link: {{link}}',
        'status' => 'sent',
        'created_by' => $user->id,
    ]);
    
    $token = bin2hex(random_bytes(32));
    PhishingTarget::create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'token' => $token,
        'status' => 'sent',
    ]);
    
    // User klik trap
    $response = $this->get(route('phishing.trap', $token));
    $response->assertOk();
    
    // Cek hanya ada 1 assignment
    $count = ModuleAssignment::where('user_id', $user->id)
        ->where('training_module_id', $remedialModule->id)
        ->count();
    
    expect($count)->toBe(1);
});

test('klik phishing trap tanpa modul remedial tidak error', function () {
    $tenant = Tenant::factory()->create();
    
    $pro = Plan::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'features' => ['training', 'phishing'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    
    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $pro->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    
    $campaign = PhishingCampaign::create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Campaign',
        'sender_name' => 'IT',
        'subject' => 'Test',
        'body_template' => 'Link: {{link}}',
        'status' => 'sent',
        'created_by' => $user->id,
    ]);
    
    $token = bin2hex(random_bytes(32));
    PhishingTarget::create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'token' => $token,
        'status' => 'sent',
    ]);
    
    // User klik trap (tidak ada modul remedial di DB)
    $response = $this->get(route('phishing.trap', $token));
    $response->assertOk();
    
    // Tidak ada assignment dibuat
    $count = ModuleAssignment::where('user_id', $user->id)->count();
    expect($count)->toBe(0);
});

test('phishing teaching page menampilkan pesan remedial saat assignment dibuat', function () {
    $tenant = Tenant::factory()->create();
    
    $pro = Plan::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro',
        'price_monthly' => 1500,
        'max_users' => 100,
        'features' => ['training', 'phishing'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    
    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $pro->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    
    $remedialModule = TrainingModule::create([
        'title' => 'Email Security',
        'content' => 'Cara mengenali phishing',
        'duration_minutes' => 30,
        'status' => 'published',
        'is_active' => true,
    ]);
    
    $campaign = PhishingCampaign::create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Campaign',
        'sender_name' => 'IT',
        'subject' => 'Test',
        'body_template' => 'Link: {{link}}',
        'status' => 'sent',
        'created_by' => $user->id,
    ]);
    
    $token = bin2hex(random_bytes(32));
    PhishingTarget::create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'token' => $token,
        'status' => 'sent',
    ]);
    
    $response = $this->get(route('phishing.trap', $token));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/PhishingTeaching')
        ->where('remedialAssigned', true)
    );
});
