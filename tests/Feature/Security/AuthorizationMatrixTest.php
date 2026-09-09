<?php

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Route;

function platformGetUris(): array
{
    return collect(Route::getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        ->filter(fn ($route) => str_starts_with($route->uri(), 'platform/'))
        ->map(fn ($route) => $route->uri())
        ->unique()
        ->values()
        ->all();
}

test('jaring authz: guest diarahkan keluar dari seluruh route platform', function () {
    foreach (platformGetUris() as $uri) {
        $resolved = preg_replace('/\{[^}]+\}/', '1', $uri);
        $status = $this->get('/'.$resolved)->getStatusCode();

        expect($status)->toBeIn([302, 401, 403, 404], "Guest menyentuh /{$uri} (status {$status})");
    }
});

test('jaring authz: role user tidak dapat membaca route platform mana pun', function () {
    $user = User::factory()->create(['role' => UserRole::User]);
    $this->actingAs($user);

    foreach (platformGetUris() as $uri) {
        $resolved = preg_replace('/\{[^}]+\}/', '1', $uri);
        $status = $this->get('/'.$resolved)->getStatusCode();

        expect($status)->toBeIn([403, 404], "Route /{$uri} bocor ke role user (status {$status})");
    }
});

test('jaring idor: tenant admin mendapat 404 untuk resource user tenant lain', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $adminA = User::factory()->create(['role' => UserRole::TenantAdmin, 'tenant_id' => $tenantA->id]);
    $userB = User::factory()->create(['role' => UserRole::User, 'tenant_id' => $tenantB->id]);

    $this->actingAs($adminA)
        ->get("/tenant/users/{$userB->id}/access")
        ->assertStatus(404);
});
