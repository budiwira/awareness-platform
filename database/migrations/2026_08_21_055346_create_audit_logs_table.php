<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->nullable()->index(); // nullable: event platform/sistem
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 120)->nullable();
            $table->string('subject_id', 40)->nullable();
            $table->jsonb('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'created_at']);
            $table->index('action');
        });

        // IMMUTABILITY LEVEL DATABASE (rekomendasi Excel #3):
        // role runtime hanya boleh INSERT dan SELECT audit log.
        // UPDATE/DELETE dicabut — bahkan jika kode aplikasi bocor/bug.
        DB::statement('REVOKE UPDATE, DELETE ON audit_logs FROM awareness_app');

        // Isolasi tenant untuk audit log
        DB::statement('ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS audit_logs_tenant_isolation ON audit_logs');
        DB::statement("
            CREATE POLICY audit_logs_tenant_isolation ON audit_logs
            FOR ALL
            USING (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR NULLIF(current_setting('app.role', true), '') = 'super_admin'
            )
            WITH CHECK (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR NULLIF(current_setting('app.role', true), '') = 'super_admin'
                OR tenant_id IS NULL
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS audit_logs_tenant_isolation ON audit_logs');
        Schema::dropIfExists('audit_logs');
    }
};