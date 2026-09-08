<?php

use App\Models\Package;
use App\Models\PackageRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BillingRequestResolved;
use Illuminate\Support\Facades\Notification;

test('super admin can view all billing requests across tenants', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $Package = Package::create(['name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 1500, 'max_users' => 100, 'is_active' => true]);

    PackageRequest::create(['tenant_id' => $tenant1->id, 'package_id' => $Package->id, 'status' => 'pending', 'requested_by' => User::factory()->tenantAdmin()->create(['tenant_id' => $tenant1->id])->id]);
    PackageRequest::create(['tenant_id' => $tenant2->id, 'package_id' => $Package->id, 'status' => 'pending', 'requested_by' => User::factory()->tenantAdmin()->create(['tenant_id' => $tenant2->id])->id]);

    $response = $this->actingAs($superAdmin)->get(route('platform.billing.requests'));

    $response->assertOk();
    $requests = $response->viewData('page')['props']['requests'];
    expect($requests)->toHaveCount(2);
});

test('super admin can approve billing request and creates active subscription', function () {
    Notification::fake();

    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    $tenantAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $Package = Package::create(['name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 1500, 'max_users' => 100, 'is_active' => true]);

    $request = PackageRequest::create([
        'tenant_id' => $tenant->id,
        'package_id' => $Package->id,
        'status' => 'pending',
        'requested_by' => $tenantAdmin->id,
    ]);

    $this->actingAs($superAdmin)
        ->post(route('platform.billing.approve'), ['request_id' => $request->id])
        ->assertRedirect(route('platform.billing.requests'));

    $request->refresh();
    expect($request->status)->toBe('approved');
    expect($request->resolved_by)->toBe($superAdmin->id);
    expect($request->resolved_at)->not->toBeNull();

    $this->assertDatabaseHas('subscriptions', [
        'tenant_id' => $tenant->id,
        'package_id' => $Package->id,
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('audit_logs', ['action' => 'billing.request_approved']);

    Notification::assertSentTo($tenantAdmin, BillingRequestResolved::class);
});

test('super admin can reject billing request', function () {
    Notification::fake();

    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    $tenantAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $Package = Package::create(['name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 1500, 'max_users' => 100, 'is_active' => true]);

    $request = PackageRequest::create([
        'tenant_id' => $tenant->id,
        'package_id' => $Package->id,
        'status' => 'pending',
        'requested_by' => $tenantAdmin->id,
    ]);

    $this->actingAs($superAdmin)
        ->post(route('platform.billing.reject'), ['request_id' => $request->id])
        ->assertRedirect(route('platform.billing.requests'));

    $request->refresh();
    expect($request->status)->toBe('rejected');
    expect($request->resolved_by)->toBe($superAdmin->id);
    expect($request->resolved_at)->not->toBeNull();

    $this->assertDatabaseMissing('subscriptions', [
        'tenant_id' => $tenant->id,
        'package_id' => $Package->id,
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('audit_logs', ['action' => 'billing.request_rejected']);

    Notification::assertSentTo($tenantAdmin, BillingRequestResolved::class);
});

test('approve fails when Package max_users below current active user count (downgrade guard)', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    $tenantAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    User::factory()->count(5)->create(['tenant_id' => $tenant->id]); // 6 active users total

    $smallPlan = Package::create(['name' => 'Small', 'slug' => 'small', 'price_monthly' => 500, 'max_users' => 3, 'is_active' => true]);

    $request = PackageRequest::create([
        'tenant_id' => $tenant->id,
        'package_id' => $smallPlan->id,
        'status' => 'pending',
        'requested_by' => $tenantAdmin->id,
    ]);

    $response = $this->actingAs($superAdmin)
        ->post(route('platform.billing.approve'), ['request_id' => $request->id]);

    $response->assertSessionHasErrors('request_id');

    $request->refresh();
    expect($request->status)->toBe('pending'); // masih pending, tidak di-approve

    $this->assertDatabaseMissing('subscriptions', [
        'tenant_id' => $tenant->id,
        'package_id' => $smallPlan->id,
        'status' => 'active',
    ]);
});

test('super admin can set Package directly via tenants page', function () {
    Notification::fake();

    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    $tenantAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $Package = Package::create(['name' => 'Enterprise', 'slug' => 'enterprise', 'price_monthly' => 5000, 'max_users' => 500, 'is_active' => true]);

    $this->actingAs($superAdmin)
        ->post(route('platform.tenants.set-package'), [
            'tenant_id' => $tenant->id,
            'package_id' => $Package->id,
        ])
        ->assertRedirect(route('platform.tenants.index'));

    $this->assertDatabaseHas('subscriptions', [
        'tenant_id' => $tenant->id,
        'package_id' => $Package->id,
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('audit_logs', ['action' => 'billing.package_set_by_admin']);

    Notification::assertSentTo($tenantAdmin, BillingRequestResolved::class);
});

test('set Package fails when Package max_users below current active user count (downgrade guard)', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    User::factory()->count(10)->create(['tenant_id' => $tenant->id]); // 10 active users

    $smallPlan = Package::create(['name' => 'Tiny', 'slug' => 'tiny', 'price_monthly' => 100, 'max_users' => 5, 'is_active' => true]);

    $response = $this->actingAs($superAdmin)
        ->post(route('platform.tenants.set-package'), [
            'tenant_id' => $tenant->id,
            'package_id' => $smallPlan->id,
        ]);

    $response->assertSessionHasErrors('package_id');

    $this->assertDatabaseMissing('subscriptions', [
        'tenant_id' => $tenant->id,
        'package_id' => $smallPlan->id,
        'status' => 'active',
    ]);
});

test('tenant admin cannot access platform billing routes', function () {
    $tenant = Tenant::factory()->create();
    $tenantAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($tenantAdmin)->get(route('platform.billing.requests'))->assertForbidden();
    $this->actingAs($tenantAdmin)->post(route('platform.billing.approve'), ['request_id' => 1])->assertForbidden();
    $this->actingAs($tenantAdmin)->post(route('platform.billing.reject'), ['request_id' => 1])->assertForbidden();
    $this->actingAs($tenantAdmin)->post(route('platform.tenants.set-package'), ['tenant_id' => 1, 'package_id' => 1])->assertForbidden();
});

test('approve cancels previous active subscription', function () {
    Notification::fake();

    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    $tenantAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $freePlan = Package::create(['name' => 'Free', 'slug' => 'free', 'price_monthly' => 0, 'max_users' => 5, 'is_active' => true]);
    $proPlan = Package::create(['name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 1500, 'max_users' => 100, 'is_active' => true]);

    // Subscription aktif lama
    $oldSub = Subscription::create([
        'tenant_id' => $tenant->id,
        'package_id' => $freePlan->id,
        'status' => 'active',
        'started_at' => now()->subDays(30),
    ]);

    $request = PackageRequest::create([
        'tenant_id' => $tenant->id,
        'package_id' => $proPlan->id,
        'status' => 'pending',
        'requested_by' => $tenantAdmin->id,
    ]);

    $this->actingAs($superAdmin)
        ->post(route('platform.billing.approve'), ['request_id' => $request->id]);

    $oldSub->refresh();
    expect($oldSub->status)->toBe('cancelled');
    expect($oldSub->ends_at)->not->toBeNull();

    $this->assertDatabaseHas('subscriptions', [
        'tenant_id' => $tenant->id,
        'package_id' => $proPlan->id,
        'status' => 'active',
    ]);
});
