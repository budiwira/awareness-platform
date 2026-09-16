<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('platform admin session remains authenticated on the next request', function () {
    $user = User::factory()->superAdmin()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->get('/dashboard')->assertRedirect(route('platform.dashboard'));
    $this->get(route('platform.dashboard'))->assertOk();
    $this->assertAuthenticatedAs($user);
});

test('tenant admin session remains authenticated on the next request', function () {
    $user = User::factory()->tenantAdmin()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->get('/dashboard')->assertRedirect(route('tenant.dashboard'));
    $this->get(route('tenant.dashboard'))->assertOk();
    $this->assertAuthenticatedAs($user);
});

test('learner session remains authenticated on the next request', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->get('/dashboard')->assertRedirect(route('user.dashboard'));
    $this->get(route('user.dashboard'))->assertOk();
    $this->assertAuthenticatedAs($user);
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('unknown email cannot authenticate', function () {
    $this->post('/login', [
        'email' => 'unknown@example.test',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('inactive users cannot authenticate', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('soft deleted users cannot authenticate', function () {
    $user = User::factory()->create();
    $user->delete();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('users can authenticate with remember me', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
    expect($user->fresh()->remember_token)->not->toBeNull();
});

test('authentication database context is cleared after failed and successful requests', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

    $failedContext = (array) DB::selectOne("SELECT current_setting('app.auth_email', true) AS auth_email, current_setting('app.user_id', true) AS user_id, current_setting('app.role', true) AS role, current_setting('app.tenant_id', true) AS tenant_id");
    expect($failedContext)->toBe(['auth_email' => '', 'user_id' => '', 'role' => '', 'tenant_id' => '']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $successfulContext = (array) DB::selectOne("SELECT current_setting('app.auth_email', true) AS auth_email, current_setting('app.user_id', true) AS user_id, current_setting('app.role', true) AS role, current_setting('app.tenant_id', true) AS tenant_id");
    expect($successfulContext)->toBe(['auth_email' => '', 'user_id' => '', 'role' => '', 'tenant_id' => '']);
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
