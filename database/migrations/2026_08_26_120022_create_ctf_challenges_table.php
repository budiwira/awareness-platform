<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ctf_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('category', 50)->default('general');
            $table->string('difficulty', 20)->default('beginner');
            $table->integer('points')->default(100);
            $table->string('flag', 255); // rahasia, tidak pernah dikirim ke client
            $table->text('hint')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        DB::statement('ALTER TABLE ctf_challenges ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS ctf_challenges_select_policy ON ctf_challenges');
        DB::statement("CREATE POLICY ctf_challenges_select_policy ON ctf_challenges FOR SELECT USING (is_active = true OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
        DB::statement('DROP POLICY IF EXISTS ctf_challenges_write_policy ON ctf_challenges');
        DB::statement("CREATE POLICY ctf_challenges_write_policy ON ctf_challenges FOR ALL WITH CHECK (NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        Schema::dropIfExists('ctf_challenges');
    }
};