<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ctf_solves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('tenant_id')->index();
            $table->foreignId('challenge_id')->constrained('ctf_challenges')->cascadeOnDelete();
            $table->integer('points');
            $table->timestamp('solved_at');
            $table->timestamps();

            $table->unique(['user_id', 'challenge_id']);
        });

        DB::statement('ALTER TABLE ctf_solves ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS ctf_solves_tenant_isolation ON ctf_solves');
        DB::statement("CREATE POLICY ctf_solves_tenant_isolation ON ctf_solves FOR ALL USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR user_id = NULLIF(current_setting('app.user_id', true), '')::bigint OR NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        Schema::dropIfExists('ctf_solves');
    }
};