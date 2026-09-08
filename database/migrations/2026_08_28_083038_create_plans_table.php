<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->integer('price_monthly')->default(0); // dalam ribuan IDR (0 = free)
            $table->integer('max_users')->default(5);
            $table->text('features')->nullable(); // json array of features
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE plans ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS plans_select_policy ON plans');
        DB::statement('CREATE POLICY plans_select_policy ON plans FOR SELECT USING (true)');
        DB::statement('DROP POLICY IF EXISTS plans_write_policy ON plans');
        DB::statement("CREATE POLICY plans_write_policy ON plans FOR ALL WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
