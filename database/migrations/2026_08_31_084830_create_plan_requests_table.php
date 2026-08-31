<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('plan_id')->constrained('plans');
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        DB::statement('ALTER TABLE plan_requests ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS plan_requests_tenant_isolation ON plan_requests');
        DB::statement("
            CREATE POLICY plan_requests_tenant_isolation ON plan_requests
            FOR SELECT
            USING (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR NULLIF(current_setting('app.role', true), '') = 'super_admin'
            )
        ");

        DB::statement('DROP POLICY IF EXISTS plan_requests_insert_policy ON plan_requests');
        DB::statement("
            CREATE POLICY plan_requests_insert_policy ON plan_requests
            FOR INSERT
            WITH CHECK (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
            )
        ");

        DB::statement('DROP POLICY IF EXISTS plan_requests_update_policy ON plan_requests');
        DB::statement("
            CREATE POLICY plan_requests_update_policy ON plan_requests
            FOR UPDATE
            USING (
                NULLIF(current_setting('app.role', true), '') = 'super_admin'
            )
            WITH CHECK (
                NULLIF(current_setting('app.role', true), '') = 'super_admin'
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_requests');
    }
};
