<?php

use App\Models\User;
use App\Models\Tenant;
use App\Models\ModuleAssignment;
use App\Models\QuizAttempt;
use App\Enums\UserRole;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['status' => 'active']);
    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => UserRole::TenantAdmin,
    ]);
    $this->user1 = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => UserRole::User]);
    $this->user2 = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => UserRole::User]);
});

test('tenant dashboard memuat agregat kesadaran', function () {
    // Buat penugasan dan attempts untuk menghitung awareness
    ModuleAssignment::factory()->create([
        'user_id' => $this->user1->id,
        'tenant_id' => $this->tenant->id,
        'status' => 'completed',
    ]);
    ModuleAssignment::factory()->create([
        'user_id' => $this->user2->id,
        'tenant_id' => $this->tenant->id,
        'status' => 'assigned',
    ]);

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
    $playbook = \App\Models\TtxPlaybook::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Playbook Test',
        'description' => 'Deskripsi test',
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
    $runbook = \App\Models\TtxRunbook::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Runbook Test',
        'description' => 'Deskripsi runbook',
        'steps' => ['Step 1', 'Step 2'],
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

    $playbook = \App\Models\TtxPlaybook::factory()->create([
        'tenant_id' => $this->tenant->id,
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

    $runbook = \App\Models\TtxRunbook::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = actingAs($otherAdmin)->get(route('tenant.ttx.runbooks.show', $runbook->id));

    $response->assertForbidden();
});
