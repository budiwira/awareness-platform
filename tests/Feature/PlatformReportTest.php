<?php

use App\Models\Tenant;
use App\Models\User;

test('super admin sees platform report with tenant rows', function () {
    $super = User::factory()->superAdmin()->create();
    Tenant::factory()->create(['name' => 'Acme Corp']);
    Tenant::factory()->create(['name' => 'Beta Corp']);

    $this->actingAs($super)
        ->get(route('platform.reports'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Dashboard')
            ->has('summary')
            ->has('top_tenants_by_risk')
        );
});

test('tenant admin cannot access platform report', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->get(route('platform.reports'))->assertForbidden();
});