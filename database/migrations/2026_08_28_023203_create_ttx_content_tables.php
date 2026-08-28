<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ttx_playbooks', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->text('content')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ttx_runbooks', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->jsonb('steps')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach (['ttx_playbooks', 'ttx_runbooks'] as $t) {
            DB::statement("ALTER TABLE $t ENABLE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS {$t}_tenant_isolation ON $t");
            DB::statement("CREATE POLICY {$t}_tenant_isolation ON $t FOR ALL USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ttx_runbooks');
        Schema::dropIfExists('ttx_playbooks');
    }
};