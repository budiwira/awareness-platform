<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_access_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Landing')
            ->has('auth')
            ->where('auth.user', null)
        );
    }

    public function test_landing_page_renders_for_guest(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Landing')
        );
    }

    public function test_authenticated_user_can_access_landing_page(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::User,
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Landing')
            ->has('auth')
            ->where('auth.user.id', $user->id)
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token')
        );
    }

    public function test_login_route_still_accessible(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_dashboard_route_redirects_by_role(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::User,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(302);
        $response->assertRedirect(route('user.dashboard'));
    }
}
