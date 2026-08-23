<?php


use App\Http\Controllers\Platform\TrainingModuleController as PlatformModuleController;
use App\Http\Controllers\Platform\TenantController as PlatformTenantController;
use App\Enums\UserRole;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Tenant\UserController as TenantUserController;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function (Request $request) {
        \Log::info('DASHBOARD_CLOSURE', ['uid' => $request->user()?->id]);

        return match ($request->user()->role) {
            UserRole::SuperAdmin => redirect()->route('platform.dashboard'),
            UserRole::TenantAdmin => redirect()->route('tenant.dashboard'),
            UserRole::User => redirect()->route('user.dashboard'),
        };
    })->name('dashboard');

    Route::middleware('can:access-platform-dashboard')
        ->prefix('platform')
        ->name('platform.')
        ->group(function () {
            Route::get('/dashboard', function () {
                return Inertia::render('Platform/Dashboard', [
                    'stats' => [
                        'tenants' => Tenant::count(),
                        'active_tenants' => Tenant::where('status', 'active')->count(),
                        'users' => User::count(),
                        'tenant_admins' => User::where('role', UserRole::TenantAdmin->value)->count(),
                    ],
                    'tenants' => Tenant::withCount('users')
                        ->orderBy('name')
                        ->get()
                        ->map(fn (Tenant $t) => [
                            'name' => $t->name,
                            'slug' => $t->slug,
                            'status' => $t->status,
                            'users_count' => $t->users_count,
                        ]),
                ]);
            })->name('dashboard');

            // Route Tenants
            Route::get('/tenants', [PlatformTenantController::class, 'index'])->name('tenants.index');
            Route::post('/tenants', [PlatformTenantController::class, 'store'])->name('tenants.store');
            
            // Route Modules ditambahkan di sini
            Route::get('/modules', [PlatformModuleController::class, 'index'])->name('modules.index');
            Route::post('/modules', [PlatformModuleController::class, 'store'])->name('modules.store');
            Route::patch('/modules/{module}', [PlatformModuleController::class, 'update'])->name('modules.update');
            Route::delete('/modules/{module}', [PlatformModuleController::class, 'destroy'])->name('modules.destroy');
            
        });

    Route::middleware('can:access-tenant-dashboard')
        ->prefix('tenant')
        ->name('tenant.')
        ->group(function () {
            Route::get('/dashboard', function (Request $request) {
                $tenantId = $request->user()->tenant_id;

                return Inertia::render('Tenant/Dashboard', [
                    'stats' => [
                        'total_users' => User::where('tenant_id', $tenantId)->count(),
                        'active_users' => User::where('tenant_id', $tenantId)->where('is_active', true)->count(),
                        'admins' => User::where('tenant_id', $tenantId)->where('role', UserRole::TenantAdmin->value)->count(),
                    ],
                ]);
            })->name('dashboard');

            // Organization & User Management (Task 5)
            Route::get('/users', [TenantUserController::class, 'index'])->name('users.index');
            Route::post('/users', [TenantUserController::class, 'store'])->name('users.store');
            Route::patch('/users/{user}', [TenantUserController::class, 'update'])->name('users.update');
            Route::post('/users/import', [TenantUserController::class, 'import'])->name('users.import');
        });

    Route::middleware('can:access-user-dashboard')
        ->prefix('me')
        ->name('user.')
        ->group(function () {
            Route::get('/dashboard', function (Request $request) {
                return Inertia::render('User/Dashboard', [
                    'tenant_name' => $request->user()->tenant?->name,
                ]);
            })->name('dashboard');
        });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';