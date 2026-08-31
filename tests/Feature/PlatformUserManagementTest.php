<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

test('super admin can view all users across tenants', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    User::factory()->count(3)->create(['tenant_id' => $tenant1->id]);
    User::factory()->count(2)->create(['tenant_id' => $tenant2->id]);

    $response = $this->actingAs($superAdmin)->get(route('platform.users.index'));

    $response->assertOk();
    $users = $response->viewData('page')['props']['users'];
    // 1 super admin + 3 tenant1 + 2 tenant2 = 6
    expect($users)->toHaveCount(6);
});

test('super admin can search users by name', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();

    User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Alice Johnson']);
    User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bob Smith']);
    User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Charlie Brown']);

    $response = $this->actingAs($superAdmin)->get(route('platform.users.index', ['search' => 'Alice']));

    $response->assertOk();
    $users = $response->viewData('page')['props']['users'];
    expect($users)->toHaveCount(1);
    expect($users[0]['name'])->toBe('Alice Johnson');
});

test('super admin can search users by email', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();

    User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'alice@example.com']);
    User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'bob@example.com']);

    $response = $this->actingAs($superAdmin)->get(route('platform.users.index', ['search' => 'alice@']));

    $response->assertOk();
    $users = $response->viewData('page')['props']['users'];
    expect($users)->toHaveCount(1);
    expect($users[0]['email'])->toBe('alice@example.com');
});

test('super admin can soft delete user from any tenant', function () {
    Notification::fake();

    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    $tenantAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $userToDelete = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($superAdmin)
        ->post(route('platform.users.destroy'), ['user_id' => $userToDelete->id])
        ->assertRedirect(route('platform.users.index'));

    $userToDelete->refresh();
    expect($userToDelete->deleted_at)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', ['action' => 'user.soft_deleted_by_admin']);

    Notification::assertSentTo($tenantAdmin, \App\Notifications\UserDeletedByAdmin::class);
});

test('super admin cannot delete themselves', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $response = $this->actingAs($superAdmin)
        ->post(route('platform.users.destroy'), ['user_id' => $superAdmin->id]);

    $response->assertSessionHasErrors('user_id');

    $superAdmin->refresh();
    expect($superAdmin->deleted_at)->toBeNull();
});

test('soft deleted user cannot login', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'test@example.com']);

    // Soft delete
    $user->delete();

    // Coba login
    $response = $this->post(route('login'), [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors();
    $this->assertGuest();
});

test('soft deleted users are included in platform users index with deleted_at', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    $activeUser = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Active User']);
    $deletedUser = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Deleted User']);

    $deletedUser->delete();

    $response = $this->actingAs($superAdmin)->get(route('platform.users.index'));

    $response->assertOk();
    $users = $response->viewData('page')['props']['users'];

    $found = collect($users)->firstWhere('name', 'Deleted User');
    expect($found)->not->toBeNull();
    expect($found['deleted_at'])->not->toBeNull();
});

test('tenant admin cannot access platform users routes', function () {
    $tenant = Tenant::factory()->create();
    $tenantAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($tenantAdmin)->get(route('platform.users.index'))->assertForbidden();
    $this->actingAs($tenantAdmin)->post(route('platform.users.destroy'), ['user_id' => 1])->assertForbidden();
});

test('regular user cannot access platform users routes', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->get(route('platform.users.index'))->assertForbidden();
    $this->actingAs($user)->post(route('platform.users.destroy'), ['user_id' => 1])->assertForbidden();
});

test('soft delete notifies all tenant admins of that tenant', function () {
    Notification::fake();

    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    $tenantAdmin1 = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id, 'name' => 'Admin 1']);
    $tenantAdmin2 = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id, 'name' => 'Admin 2']);
    $userToDelete = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($superAdmin)
        ->post(route('platform.users.destroy'), ['user_id' => $userToDelete->id]);

    Notification::assertSentTo([$tenantAdmin1, $tenantAdmin2], \App\Notifications\UserDeletedByAdmin::class);
});

test('soft delete does not notify tenant admins from other tenants', function () {
    Notification::fake();

    $superAdmin = User::factory()->superAdmin()->create();
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $tenantAdmin1 = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant1->id]);
    $tenantAdmin2 = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant2->id]);
    $userToDelete = User::factory()->create(['tenant_id' => $tenant1->id]);

    $this->actingAs($superAdmin)
        ->post(route('platform.users.destroy'), ['user_id' => $userToDelete->id]);

    Notification::assertSentTo($tenantAdmin1, \App\Notifications\UserDeletedByAdmin::class);
    Notification::assertNotSentTo($tenantAdmin2, \App\Notifications\UserDeletedByAdmin::class);
});

test('downgrade guard counts only non-deleted users', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    $tenantAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

    // 5 active + 3 deleted = 8 total, tapi hanya 5 yang count
    User::factory()->count(4)->create(['tenant_id' => $tenant->id]); // +4 = 5 active (termasuk tenantAdmin)
    $deleted = User::factory()->count(3)->create(['tenant_id' => $tenant->id]);
    foreach ($deleted as $u) {
        $u->delete();
    }

    $smallPlan = \App\Models\Plan::create(['name' => 'Small', 'slug' => 'small', 'price_monthly' => 500, 'max_users' => 5, 'is_active' => true]);

    // Harus sukses karena hanya 5 active users (deleted tidak dihitung)
    $response = $this->actingAs($superAdmin)
        ->post(route('platform.tenants.set-plan'), [
            'tenant_id' => $tenant->id,
            'plan_id' => $smallPlan->id,
        ]);

    $response->assertRedirect(route('platform.tenants.index'));

    $this->assertDatabaseHas('subscriptions', [
        'tenant_id' => $tenant->id,
        'plan_id' => $smallPlan->id,
        'status' => 'active',
    ]);
});
