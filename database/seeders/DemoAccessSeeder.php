<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoAccessSeeder extends Seeder
{
    public function run(): void
    {
        // Bypass RLS untuk seeding
        DB::statement('SET LOCAL row_security = off;');

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'pt-demo'],
            ['name' => 'PT Demo Nusantara']
        );

        $package = Package::where('slug', 'pro')->first();

        Subscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['package_id' => $package?->id, 'status' => 'active', 'started_at' => now()]
        );

        $users = [
            ['name' => 'Admin Demo', 'email' => 'admin@demo.io', 'role' => 'tenant_admin'],
            ['name' => 'Budi User', 'email' => 'budi@demo.io', 'role' => 'user'],
            ['name' => 'Sari User', 'email' => 'sari@demo.io', 'role' => 'user'],
        ];

        foreach ($users as $u) {
            User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'role' => $u['role'],
                    'tenant_id' => $tenant->id,
                    'password' => Hash::make('demo1234'),
                    'is_active' => true,
                ]
            );
        }

        // Re-enable RLS
        DB::statement('SET LOCAL row_security = on;');

        echo "Seeded: ".$tenant->name." | users: ".$tenant->users()->count()."\n";
    }
}