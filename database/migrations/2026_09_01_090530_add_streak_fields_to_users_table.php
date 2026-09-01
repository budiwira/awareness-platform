<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Disable RLS untuk ALTER TABLE
        DB::statement('ALTER TABLE users DISABLE ROW LEVEL SECURITY');

        DB::statement('ALTER TABLE users ADD COLUMN login_streak INTEGER DEFAULT 0 NOT NULL');
        DB::statement('ALTER TABLE users ADD COLUMN last_login_date DATE NULL');

        // Re-enable RLS
        DB::statement('ALTER TABLE users ENABLE ROW LEVEL SECURITY');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DISABLE ROW LEVEL SECURITY');

        DB::statement('ALTER TABLE users DROP COLUMN login_streak');
        DB::statement('ALTER TABLE users DROP COLUMN last_login_date');

        DB::statement('ALTER TABLE users ENABLE ROW LEVEL SECURITY');
    }
};
