<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_modules', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->text('content');
            $table->integer('duration_minutes')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        DB::statement('ALTER TABLE training_modules ENABLE ROW LEVEL SECURITY');
        
        DB::statement('DROP POLICY IF EXISTS training_modules_select_policy ON training_modules');
        DB::statement("
            CREATE POLICY training_modules_select_policy ON training_modules
            FOR SELECT
            USING (is_active = true OR NULLIF(current_setting('app.role', true), '') = 'super_admin')
        ");

        DB::statement('DROP POLICY IF EXISTS training_modules_write_policy ON training_modules');
        DB::statement("
            CREATE POLICY training_modules_write_policy ON training_modules
            FOR ALL
            WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('training_modules');
    }
};