<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('platform dashboard exposes real platform stats', function () {
    $super = User::factory()->superAdmin()->create();
    Tenant::factory()->create(['name' => 'Acme Corp']);
    Tenant::factory()->create(['name' => 'Beta Corp']);

    $this->actingAs($super)
        ->get(route('platform.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Dashboard')
            ->where('summary.total_tenants', 2)
            ->has('summary.total_users')
            ->has('summary.avg_platform_awareness_score')
            ->has('top_tenants_by_risk')
            ->has('plan_distribution')
            ->has('phishing_adoption.tenants_with_phishing_feature')
        );
});

test('tenant dashboard only sees its own tenant users', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    User::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
    User::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenantA->id]);

    $this->actingAs($admin)
        ->get(route('tenant.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Dashboard')
            // admin + 2 user tenant A = 3. Jika bocor dari tenant B, angka ini salah.
            ->where('stats.total_users', 3)
        );
});