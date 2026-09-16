<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

test('tenant admin lists only own tenant users', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenantA->id, 'name' => 'Admin A']);
    User::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Member A']);
    User::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Member B']);

    $response = $this->actingAs($admin)->get(route('tenant.users.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Users/Index')
            ->has('users', 1)
        );

    $html = $response->getContent();
    expect($html)->toContain('Member A')->not->toContain('Member B');
});

test('tenant admin creates learner in own tenant and ignores injected tenant and role', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenantA->id]);

    $this->actingAs($admin)->post(route('tenant.users.store'), [
        'name' => 'New Member',
        'email' => 'new@member.local',
        'role' => 'tenant_admin',
        'tenant_id' => $tenantB->id, // percobaan injeksi — harus diabaikan
    ])->assertRedirect(route('tenant.users.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('users', [
        'email' => 'new@member.local',
        'tenant_id' => $tenantA->id,
        'role' => 'user',
    ]);

    $this->assertDatabaseHas('audit_logs', ['action' => 'user.created']);
});

test('tenant admin request cannot create privileged user', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->post(route('tenant.users.store'), [
        'name' => 'Evil',
        'email' => 'evil@local',
        'role' => 'super_admin',
    ])->assertRedirect(route('tenant.users.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'evil@local',
        'role' => 'user',
        'tenant_id' => $admin->tenant_id,
    ]);
});

test('tenant admin cannot update user from another tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenantA->id]);
    $victim = User::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($admin)->patch(route('tenant.users.update', $victim), [
        'name' => 'Hacked',
        'email' => $victim->email,
        'role' => 'user',
        'is_active' => false,
    ])->assertForbidden();
});

test('tenant admin cannot change own role', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->patch(route('tenant.users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => 'user',
        'is_active' => true,
    ])->assertForbidden();
});

test('tenant admin cannot disable own account', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->patch(route('tenant.users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => $admin->role->value,
        'is_active' => false,
    ])->assertForbidden();
});

test('regular user cannot access tenant user management', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('tenant.users.index'))->assertForbidden();
});

test('tenant admin can deactivate learner without changing role', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $member = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)->patch(route('tenant.users.update', $member), [
        'name' => $member->name,
        'email' => $member->email,
        'role' => 'tenant_admin',
        'is_active' => false,
    ])->assertRedirect(route('tenant.users.index'));

    $this->assertDatabaseHas('users', ['id' => $member->id, 'role' => 'user', 'is_active' => false]);
    $this->assertDatabaseMissing('audit_logs', ['action' => 'user.role_changed']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.disabled']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated']);
});

test('tenant admin cannot promote a learner through a manipulated update', function () {
    $admin = User::factory()->tenantAdmin()->create();
    $member = User::factory()->create(['tenant_id' => $admin->tenant_id]);
    $this->actingAs($admin)->patch(route('tenant.users.update', $member), [
        'name' => 'Changed',
        'email' => $member->email,
        'role' => 'tenant_admin',
        'is_active' => true,
    ])->assertRedirect(route('tenant.users.index'));

    expect($member->fresh()->role->value)->toBe('user')
        ->and($member->fresh()->name)->toBe('Changed');
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated']);
});

test('tenant admin updates member profile without accepting injected tenant_id', function () {
    $admin = User::factory()->tenantAdmin()->create();
    $member = User::factory()->create(['tenant_id' => $admin->tenant_id]);
    $otherTenant = Tenant::factory()->create();

    $this->actingAs($admin)->patch(route('tenant.users.update', $member), [
        'name' => 'Updated Member',
        'email' => $member->email,
        'role' => 'user',
        'is_active' => true,
        'tenant_id' => $otherTenant->id,
    ])->assertRedirect(route('tenant.users.index'));

    $this->assertDatabaseHas('users', [
        'id' => $member->id,
        'name' => 'Updated Member',
        'tenant_id' => $admin->tenant_id,
    ]);
});

test('regular user cannot create tenant users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('tenant.users.store'), [
        'name' => 'Unauthorized Member',
        'email' => 'unauthorized@example.com',
        'role' => 'tenant_admin',
    ])->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'unauthorized@example.com']);
});

test('regular user cannot update tenant users', function (bool $self) {
    $user = User::factory()->create();
    $target = $self ? $user : User::factory()->create(['tenant_id' => $user->tenant_id]);
    $original = $target->fresh()->getAttributes();

    $this->actingAs($user)->patch(route('tenant.users.update', $target), [
        'name' => 'Unauthorized Change',
        'email' => $target->email,
        'role' => 'tenant_admin',
        'is_active' => true,
    ])->assertForbidden();

    expect($target->fresh()->getAttributes())->toBe($original);
})->with(['self' => true, 'same tenant member' => false]);

test('regular user cannot import tenant users', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent(
        'users.csv', "name,email\nUnauthorized Member,imported@example.com\n"
    );

    $this->actingAs($user)->post(route('tenant.users.import'), [
        'file' => $file,
    ])->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'imported@example.com']);
});
