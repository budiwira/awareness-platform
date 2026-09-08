<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\TrainingModule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder bertindak sebagai platform (super admin) agar lolos RLS write policy
        DB::statement("SELECT set_config('app.role', 'super_admin', false)");

        // Ambil modul published untuk kurasi
        $modules = TrainingModule::where('status', 'published')->get();
        $basicModules = $modules->take(3); // 3 modul dasar untuk Starter

        $packages = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price_monthly' => 0,
                'is_free' => true,
                'max_users' => 5,
                'features' => ['training'],
                'includes_all_modules' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price_monthly' => 1500,
                'is_free' => false,
                'max_users' => 50,
                'features' => ['training', 'reports_export', 'ttx', 'case_studies', 'phishing'],
                'includes_all_modules' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price_monthly' => 5000,
                'is_free' => false,
                'max_users' => null,
                'features' => ['training', 'reports_export', 'ttx', 'case_studies', 'ctf', 'phishing'],
                'includes_all_modules' => true,
                'is_active' => true,
            ],
        ];

        foreach ($packages as $packageData) {
            $package = Package::updateOrCreate(['slug' => $packageData['slug']], $packageData);

            // Attach modul kurasi untuk Free (package lain includes_all_modules=true)
            if ($packageData['slug'] === 'free' && ! $packageData['includes_all_modules']) {
                $package->modules()->sync($basicModules->pluck('id')->toArray());
            } elseif ($packageData['includes_all_modules']) {
                $package->modules()->detach(); // Kosongkan pivot, karena includes_all_modules=true
            }
        }
    }
}
