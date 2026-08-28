<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder bertindak sebagai platform (super admin) agar lolos RLS write policy
        DB::statement("SELECT set_config('app.role', 'super_admin', false)");

        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price_monthly' => 0,
                'max_users' => 5,
                'features' => ['5 users', 'Basic training', 'Case study access'],
                'is_active' => true,
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price_monthly' => 500,
                'max_users' => 25,
                'features' => ['25 users', 'All training modules', 'CTF access', 'Basic reporting'],
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price_monthly' => 1500,
                'max_users' => 100,
                'features' => ['100 users', 'All features', 'TTX exercises', 'Advanced reporting', 'Priority support'],
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price_monthly' => 5000,
                'max_users' => 9999,
                'features' => ['Unlimited users', 'All features', 'Custom TTX', 'Dedicated support', 'SLA'],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}