<?php

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;

test('jaring seeder: setiap tenant memiliki minimal satu tenant_admin', function () {
    $this->seed();

    $tenants = Tenant::all();
    expect($tenants)->not->toBeEmpty();

    foreach ($tenants as $tenant) {
        $adminCount = User::where('tenant_id', $tenant->id)
            ->where('role', UserRole::TenantAdmin)
            ->count();

        expect($adminCount)->toBeGreaterThan(
            0,
            "Tenant {$tenant->slug} (id: {$tenant->id}) tidak punya tenant_admin - seeder tidak konsisten."
        );
    }
});

test('jaring seeder: super admin tidak memiliki tenant_id', function () {
    $this->seed();

    $superAdmins = User::where('role', UserRole::SuperAdmin)->get();

    foreach ($superAdmins as $sa) {
        expect($sa->tenant_id)->toBeNull(
            "Super admin {$sa->email} punya tenant_id={$sa->tenant_id} - melanggar model data."
        );
    }
});

test('jaring seeder: non-super-admin memiliki tenant_id', function () {
    $this->seed();

    $orphans = User::where('role', '!=', UserRole::SuperAdmin)
        ->whereNull('tenant_id')
        ->get();

    expect($orphans)->toBeEmpty(
        'Ditemukan '.count($orphans).' user non-super-admin tanpa tenant_id (orphan).'
    );
});
