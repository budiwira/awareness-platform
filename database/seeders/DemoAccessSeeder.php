<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccessSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::on('pgsql_seeder')->firstOrCreate(
            ['slug' => 'pt-demo'],
            ['name' => 'PT Demo Nusantara']
        );

        $package = Package::on('pgsql_seeder')->where('slug', 'pro')->first();

        Subscription::on('pgsql_seeder')->updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['package_id' => $package?->id, 'status' => 'active', 'started_at' => now()]
        );

        $users = [
            ['name' => 'Admin Demo', 'email' => 'admin@demo.io', 'role' => 'tenant_admin'],
            ['name' => 'Budi User', 'email' => 'budi@demo.io', 'role' => 'user'],
            ['name' => 'Sari User', 'email' => 'sari@demo.io', 'role' => 'user'],
        ];

        foreach ($users as $u) {
            User::on('pgsql_seeder')->firstOrCreate(
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

        $this->call(TtxFlagshipScenarioSeeder::class);

        $count = User::on('pgsql_seeder')->where('tenant_id', $tenant->id)->count();

        echo "Seeded: {$tenant->name} | users: {$count}\n";
    }
}
