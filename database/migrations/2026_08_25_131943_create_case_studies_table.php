<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_studies', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('difficulty', 20)->default('beginner');
            $table->integer('duration_minutes')->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        DB::statement('ALTER TABLE case_studies ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS case_studies_select_policy ON case_studies');
        DB::statement("CREATE POLICY case_studies_select_policy ON case_studies FOR SELECT USING (is_active = true OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
        DB::statement('DROP POLICY IF EXISTS case_studies_write_policy ON case_studies');
        DB::statement("CREATE POLICY case_studies_write_policy ON case_studies FOR ALL WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        Schema::dropIfExists('case_studies');
    }
};