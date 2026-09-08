<?php

use App\Enums\UserRole;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TtxPlaybook;
use App\Models\TtxRunbook;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['status' => 'active']);
    $Package = Package::create(['name' => 'Agg-'.uniqid(), 'slug' => 'agg-'.uniqid(), 'price_monthly' => 100, 'max_users' => 100, 'features' => ['training', 'ttx'], 'includes_all_modules' => true, 'is_active' => true]);
    Subscription::create(['tenant_id' => $this->tenant->id, 'package_id' => $Package->id, 'status' => 'active', 'started_at' => now()]);
    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => UserRole::TenantAdmin,
    ]);
    $this->user1 = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => UserRole::User]);
    $this->user2 = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => UserRole::User]);
});

test('tenant dashboard memuat agregat kesadaran', function () {
    $response = actingAs($this->admin)->get(route('tenant.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Dashboard')
        ->has('stats')
        ->has('stats.avg_awareness_score')
        ->has('stats.completion_rate')
        ->has('stats.tier_baik')
        ->has('stats.tier_cukup')
        ->has('stats.tier_perlu_perbaikan')
        ->has('stats.tier_belum_mengerjakan')
    );
});

test('tenant admin dapat membuka playbook detail', function () {
    $playbook = TtxPlaybook::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Playbook Test',
        'description' => 'Deskripsi test',
        'content' => 'Prosedur test',
        'is_active' => true,
    ]);

    $response = actingAs($this->admin)->get(route('tenant.ttx.playbooks.show', $playbook->id));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Ttx/PlaybookShow')
        ->has('playbook')
        ->where('playbook.id', $playbook->id)
        ->where('playbook.title', 'Playbook Test')
    );
});

test('tenant admin dapat membuka runbook detail', function () {
    $runbook = TtxRunbook::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Runbook Test',
        'description' => 'Deskripsi runbook',
        'steps' => ['Step 1', 'Step 2'],
        'is_active' => true,
    ]);

    $response = actingAs($this->admin)->get(route('tenant.ttx.runbooks.show', $runbook->id));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tenant/Ttx/RunbookShow')
        ->has('runbook')
        ->where('runbook.id', $runbook->id)
        ->where('runbook.title', 'Runbook Test')
    );
});

test('tenant lain tidak dapat membuka playbook tenant lain', function () {
    $otherTenant = Tenant::factory()->create(['status' => 'active']);
    $otherAdmin = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'role' => UserRole::TenantAdmin,
    ]);

    $playbook = TtxPlaybook::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Playbook Tenant 1',
        'description' => 'Test',
        'content' => 'Content',
        'is_active' => true,
    ]);

    $response = actingAs($otherAdmin)->get(route('tenant.ttx.playbooks.show', $playbook->id));

    $response->assertForbidden();
});

test('tenant lain tidak dapat membuka runbook tenant lain', function () {
    $otherTenant = Tenant::factory()->create(['status' => 'active']);
    $otherAdmin = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'role' => UserRole::TenantAdmin,
    ]);

    $runbook = TtxRunbook::create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Runbook Tenant 1',
        'description' => 'Test',
        'steps' => ['Step 1'],
        'is_active' => true,
    ]);

    $response = actingAs($otherAdmin)->get(route('tenant.ttx.runbooks.show', $runbook->id));

    $response->assertForbidden();
});
