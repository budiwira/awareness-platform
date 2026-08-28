<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $acme = Tenant::create(['name' => 'Acme Corporation', 'slug' => 'acme', 'status' => 'active']);
        $beta = Tenant::create(['name' => 'Beta Nusantara', 'slug' => 'beta', 'status' => 'active']);

        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@platform.local',
            'password' => 'password',
            'role' => UserRole::SuperAdmin,
            'tenant_id' => null,
            'is_active' => true,
        ]);

        foreach ([$acme, $beta] as $tenant) {
            User::create([
                'name' => 'Admin '.$tenant->name,
                'email' => 'admin@'.$tenant->slug.'.local',
                'password' => 'password',
                'role' => UserRole::TenantAdmin,
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]);

            User::create([
                'name' => 'User '.$tenant->name,
                'email' => 'user@'.$tenant->slug.'.local',
                'password' => 'password',
                'role' => UserRole::User,
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]);
        }
        $this->call(TtxContentSeeder::class);
        $this->call(PlanSeeder::class);  

    }
}