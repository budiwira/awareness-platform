<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenant\CurrentTenant;

test('tenant context is set from the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('user.dashboard'))->assertOk();

    expect(app(CurrentTenant::class)->id())->toBe($user->tenant_id);
});

test('tenant context ignores tenant_id injected in request', function () {
    $user = User::factory()->create();
    $evilTenant = Tenant::factory()->create();

    $this->actingAs($user)
        ->get(route('user.dashboard', ['tenant_id' => $evilTenant->id]))
        ->assertOk();

    expect(app(CurrentTenant::class)->id())->toBe($user->tenant_id);
});

test('super admin has no tenant context', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)->get(route('platform.dashboard'))->assertOk();

    expect(app(CurrentTenant::class)->isSet())->toBeFalse();
});