<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_module', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->onDelete('cascade');
            $table->foreignId('training_module_id')->constrained('training_modules')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['plan_id', 'training_module_id']);
        });

        DB::statement('ALTER TABLE plan_module ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS plan_module_select_policy ON plan_module');
        DB::statement("CREATE POLICY plan_module_select_policy ON plan_module FOR SELECT USING (true)");
        DB::statement('DROP POLICY IF EXISTS plan_module_write_policy ON plan_module');
        DB::statement("CREATE POLICY plan_module_write_policy ON plan_module FOR ALL WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_module');
    }
};
