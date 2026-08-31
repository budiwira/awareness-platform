<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Matikan RLS sementara untuk ALTER TABLE
        DB::statement('ALTER TABLE training_modules DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE case_studies DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ctf_challenges DISABLE ROW LEVEL SECURITY');

        Schema::table('training_modules', function (Blueprint $table) {
            $table->string('status', 20)->default('published')->after('is_active');
            $table->index('status');
        });

        Schema::table('case_studies', function (Blueprint $table) {
            $table->string('status', 20)->default('published')->after('is_active');
            $table->index('status');
        });

        Schema::table('ctf_challenges', function (Blueprint $table) {
            $table->string('status', 20)->default('published')->after('is_active');
            $table->index('status');
        });

        // Nyalakan kembali RLS
        DB::statement('ALTER TABLE training_modules ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE case_studies ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ctf_challenges ENABLE ROW LEVEL SECURITY');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE training_modules DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE case_studies DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ctf_challenges DISABLE ROW LEVEL SECURITY');

        Schema::table('training_modules', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });

        Schema::table('case_studies', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });

        Schema::table('ctf_challenges', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });

        DB::statement('ALTER TABLE training_modules ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE case_studies ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ctf_challenges ENABLE ROW LEVEL SECURITY');
    }
};
