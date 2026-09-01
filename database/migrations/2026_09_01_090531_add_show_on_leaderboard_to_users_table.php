<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Disable RLS untuk ALTER TABLE
        DB::statement('ALTER TABLE users DISABLE ROW LEVEL SECURITY');

        DB::statement('ALTER TABLE users ADD COLUMN show_on_leaderboard BOOLEAN DEFAULT TRUE NOT NULL');

        // Re-enable RLS
        DB::statement('ALTER TABLE users ENABLE ROW LEVEL SECURITY');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DISABLE ROW LEVEL SECURITY');

        DB::statement('ALTER TABLE users DROP COLUMN show_on_leaderboard');

        DB::statement('ALTER TABLE users ENABLE ROW LEVEL SECURITY');
    }
};
