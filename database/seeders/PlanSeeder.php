<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\TrainingModule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder bertindak sebagai platform (super admin) agar lolos RLS write policy
        DB::statement("SELECT set_config('app.role', 'super_admin', false)");

        // Ambil modul published untuk kurasi
        $modules = TrainingModule::where('status', 'published')->get();
        $basicModules = $modules->take(3); // 3 modul dasar untuk Starter

        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price_monthly' => 500,
                'max_users' => 25,
                'features' => ['training'],
                'includes_all_modules' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price_monthly' => 1500,
                'max_users' => 100,
                'features' => ['training', 'reports_export', 'ttx', 'case_studies', 'phishing'],
                'includes_all_modules' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price_monthly' => 5000,
                'max_users' => 9999,
                'features' => ['training', 'reports_export', 'ttx', 'case_studies', 'ctf', 'phishing'],
                'includes_all_modules' => true,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $planData) {
            $plan = Plan::updateOrCreate(['slug' => $planData['slug']], $planData);

            // Attach modul kurasi untuk Starter (plan lain includes_all_modules=true)
            if ($planData['slug'] === 'starter' && !$planData['includes_all_modules']) {
                $plan->modules()->sync($basicModules->pluck('id')->toArray());
            } elseif ($planData['includes_all_modules']) {
                $plan->modules()->detach(); // Kosongkan pivot, karena includes_all_modules=true
            }
        }
    }
}
