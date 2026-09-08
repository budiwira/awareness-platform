<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('tenant_id')->index();
            $table->foreignId('training_module_id')->constrained('training_modules')->cascadeOnDelete();

            $table->string('status', 20)->default('assigned');
            $table->timestamp('completed_at')->nullable();
            $table->integer('score')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'training_module_id']);
        });

        DB::statement('ALTER TABLE module_assignments ENABLE ROW LEVEL SECURITY');

        DB::statement('DROP POLICY IF EXISTS module_assignments_tenant_isolation ON module_assignments');
        DB::statement("
            CREATE POLICY module_assignments_tenant_isolation ON module_assignments
            FOR ALL
            USING (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR user_id = NULLIF(current_setting('app.user_id', true), '')::bigint
                OR NULLIF(current_setting('app.role', true), '') = 'super_admin'
            )
            WITH CHECK (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR NULLIF(current_setting('app.role', true), '') = 'super_admin'
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('module_assignments');
    }
};
