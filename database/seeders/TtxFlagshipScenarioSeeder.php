<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TtxFlagshipScenarioSeeder extends Seeder
{
    public function run(): void
    {
        // This is a controlled platform provisioning operation. Tenant runtime
        // queries remain protected by their normal RLS context and policies.
        DB::statement("SELECT set_config('app.role', 'super_admin', false)");

        $content = new TtxContentSeeder;

        Tenant::query()
            ->whereIn('slug', TtxContentSeeder::FLAGSHIP_TENANT_SLUGS)
            ->orderBy('slug')
            ->each(fn (Tenant $tenant) => $content->provisionFlagshipScenario($tenant));
    }
}
