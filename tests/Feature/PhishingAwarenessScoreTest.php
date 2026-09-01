<?php

use App\Models\PhishingCampaign;
use App\Models\PhishingTarget;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Scoring\AwarenessScore;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
});

test('user tanpa kampanye phishing mendapat score 100 untuk sinyal phishing', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

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

    $scorer = new AwarenessScore();
    $result = $scorer->compute(
        collect(),
        collect(),
        collect(),
        collect(),
        collect(),
        0,
        ['training', 'phishing'],
        collect() // No phishing targets
    );

    $phishingBreakdown = collect($result['breakdown'])->firstWhere('key', 'phishing_awareness');
    expect($phishingBreakdown)->not->toBeNull();
    expect($phishingBreakdown['score'])->toBe(100);
    expect($phishingBreakdown['locked'])->toBe(false);
});

test('user dengan 1 click dari 1 campaign mendapat score 50', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

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

    $campaign = PhishingCampaign::create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Campaign',
        'sender_name' => 'IT',
        'subject' => 'Test',
        'body_template' => 'Link: {{link}}',
        'status' => 'sent',
        'created_by' => $user->id,
    ]);

    PhishingTarget::create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'token' => bin2hex(random_bytes(32)),
        'status' => 'clicked',
        'clicked_at' => now(),
    ]);

    $targets = PhishingTarget::where('user_id', $user->id)->get();

    $scorer = new AwarenessScore();
    $result = $scorer->compute(
        collect(),
        collect(),
        collect(),
        collect(),
        collect(),
        0,
        ['training', 'phishing'],
        $targets
    );

    $phishingBreakdown = collect($result['breakdown'])->firstWhere('key', 'phishing_awareness');
    expect($phishingBreakdown['score'])->toBe(50); // 100 - (100% * 50)
});

test('tenant tanpa fitur phishing tidak punya sinyal phishing dan bobot renormalisasi', function () {
    $tenant = Tenant::factory()->create();

    $starter = Plan::firstOrCreate(['slug' => 'starter'], [
        'name' => 'Starter',
        'price_monthly' => 500,
        'max_users' => 25,
        'features' => ['training'],
        'includes_all_modules' => false,
        'is_active' => true,
    ]);

    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $starter->id,
        'status' => 'active',
        'started_at' => now(),
    ]);

    $scorer = new AwarenessScore();
    $result = $scorer->compute(
        collect(),
        collect(),
        collect(),
        collect(),
        collect(),
        0,
        ['training'], // No phishing
        collect()
    );

    $phishingBreakdown = collect($result['breakdown'])->firstWhere('key', 'phishing_awareness');
    expect($phishingBreakdown['locked'])->toBe(true);
    expect($phishingBreakdown['note'])->toBe('Tidak termasuk dalam plan');

    // Bobot training sinyal harus naik karena renormalisasi
    $completionBreakdown = collect($result['breakdown'])->firstWhere('key', 'completion');
    expect($completionBreakdown['weight'])->toBeGreaterThan(20); // Original 20%, now renormalized
});

test('user dengan 2 click dari 4 campaign mendapat score 75', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

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

    $campaign = PhishingCampaign::create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Campaign',
        'sender_name' => 'IT',
        'subject' => 'Test',
        'body_template' => 'Link: {{link}}',
        'status' => 'sent',
        'created_by' => $user->id,
    ]);

    // 2 clicked, 2 sent
    PhishingTarget::create(['campaign_id' => $campaign->id, 'user_id' => $user->id, 'token' => bin2hex(random_bytes(32)), 'status' => 'clicked', 'clicked_at' => now()]);
    PhishingTarget::create(['campaign_id' => $campaign->id, 'user_id' => $user->id, 'token' => bin2hex(random_bytes(32)), 'status' => 'clicked', 'clicked_at' => now()]);
    PhishingTarget::create(['campaign_id' => $campaign->id, 'user_id' => $user->id, 'token' => bin2hex(random_bytes(32)), 'status' => 'sent']);
    PhishingTarget::create(['campaign_id' => $campaign->id, 'user_id' => $user->id, 'token' => bin2hex(random_bytes(32)), 'status' => 'sent']);

    $targets = PhishingTarget::where('user_id', $user->id)->get();

    $scorer = new AwarenessScore();
    $result = $scorer->compute(
        collect(),
        collect(),
        collect(),
        collect(),
        collect(),
        0,
        ['training', 'phishing'],
        $targets
    );

    $phishingBreakdown = collect($result['breakdown'])->firstWhere('key', 'phishing_awareness');
    // click_rate = 2/4 = 50%, score = 100 - (50 * 0.5) = 75
    expect($phishingBreakdown['score'])->toBe(75);
});
