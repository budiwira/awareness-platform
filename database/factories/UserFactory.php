<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
{
    return [
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => static::$password ??= Hash::make('password'),
        'remember_token' => Str::random(10),
        // PENTING: Penuhi constraint database kita
        'tenant_id' => \App\Models\Tenant::factory(), 
        'role' => UserRole::User->value,
        'is_active' => true,
    ];
}

// Tambahkan helper ini di dalam class UserFactory (sebelum kurung kurawal tutup)
public function superAdmin(): static
{
    return $this->state(fn (array $attributes) => [
        'role' => UserRole::SuperAdmin->value,
        'tenant_id' => null, // Super admin tidak punya tenant
    ]);
}

public function tenantAdmin(): static
{
    return $this->state(fn (array $attributes) => [
        'role' => UserRole::TenantAdmin->value,
    ]);
}

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
