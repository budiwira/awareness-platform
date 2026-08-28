<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;

test('tenant admin can subscribe to a plan', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $pro = Plan::create(['name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 1500, 'max_users' => 100]);

    $this->actingAs($admin)
        ->post(route('tenant.billing.subscribe'), ['plan_id' => $pro->id])
        ->assertRedirect(route('tenant.billing.index'));

    $this->assertDatabaseHas('subscriptions', [
        'tenant_id' => $tenant->id,
        'plan_id' => $pro->id,
        'status' => 'active',
    ]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'billing.plan_changed']);
});

test('cannot downgrade below current user count', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    User::factory()->count(3)->create(['tenant_id' => $tenant->id]); // total 4 users
    $tiny = Plan::create(['name' => 'Tiny', 'slug' => 'tiny', 'price_monthly' => 100, 'max_users' => 2]);

    $this->actingAs($admin)
        ->post(route('tenant.billing.subscribe'), ['plan_id' => $tiny->id])
        ->assertSessionHasErrors('plan_id');

    $this->assertDatabaseMissing('subscriptions', ['plan_id' => $tiny->id, 'status' => 'active']);
});

test('switching plan cancels previous active subscription', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $free = Plan::create(['name' => 'Free', 'slug' => 'free', 'price_monthly' => 0, 'max_users' => 5]);
    $pro = Plan::create(['name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 1500, 'max_users' => 100]);

    Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $free->id, 'status' => 'active', 'started_at' => now()]);

    $this->actingAs($admin)
        ->post(route('tenant.billing.subscribe'), ['plan_id' => $pro->id])
        ->assertRedirect();

    $this->assertDatabaseHas('subscriptions', ['plan_id' => $free->id, 'status' => 'cancelled']);
    $this->assertDatabaseHas('subscriptions', ['plan_id' => $pro->id, 'status' => 'active']);
});

test('regular user cannot access billing', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->get(route('tenant.billing.index'))->assertForbidden();
});