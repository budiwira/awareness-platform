<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('access-platform-dashboard', function (User $user): bool {
            return $user->is_active && $user->role === UserRole::SuperAdmin;
        });

        Gate::define('access-tenant-dashboard', function (User $user): bool {
            return $user->is_active
                && $user->role === UserRole::TenantAdmin
                && $user->tenant_id !== null;
        });

        Gate::define('access-user-dashboard', function (User $user): bool {
            return $user->is_active
                && $user->role === UserRole::User
                && $user->tenant_id !== null;
        });
    }
}