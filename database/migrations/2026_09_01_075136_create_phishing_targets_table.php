<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phishing_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('phishing_campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('status', 20)->default('sent'); // sent|clicked
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();
            
            $table->index(['campaign_id', 'status']);
        });

        DB::statement('ALTER TABLE phishing_targets ENABLE ROW LEVEL SECURITY');
        
        DB::statement('DROP POLICY IF EXISTS phishing_targets_tenant_isolation ON phishing_targets');
        DB::statement("
            CREATE POLICY phishing_targets_tenant_isolation ON phishing_targets
            FOR ALL
            USING (
                EXISTS (
                    SELECT 1 FROM phishing_campaigns pc
                    WHERE pc.id = phishing_targets.campaign_id
                    AND (
                        pc.tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                        OR NULLIF(current_setting('app.role', true), '') = 'super_admin'
                    )
                )
                OR user_id = NULLIF(current_setting('app.user_id', true), '')::bigint
            )
            WITH CHECK (
                EXISTS (
                    SELECT 1 FROM phishing_campaigns pc
                    WHERE pc.id = phishing_targets.campaign_id
                    AND pc.tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                )
                OR NULLIF(current_setting('app.role', true), '') = 'super_admin'
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('phishing_targets');
    }
};
