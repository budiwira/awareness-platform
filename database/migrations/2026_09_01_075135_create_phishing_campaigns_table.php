<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phishing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->string('title');
            $table->string('sender_name');
            $table->string('subject');
            $table->text('body_template');
            $table->string('status', 20)->default('draft'); // draft|running|completed
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('email_snapshot')->nullable(); // JSON: {subject, body}
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        DB::statement('ALTER TABLE phishing_campaigns ENABLE ROW LEVEL SECURITY');
        
        DB::statement('DROP POLICY IF EXISTS phishing_campaigns_tenant_isolation ON phishing_campaigns');
        DB::statement("
            CREATE POLICY phishing_campaigns_tenant_isolation ON phishing_campaigns
            FOR SELECT
            USING (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                OR NULLIF(current_setting('app.role', true), '') = 'super_admin'
            )
        ");
        
        DB::statement('DROP POLICY IF EXISTS phishing_campaigns_insert_policy ON phishing_campaigns');
        DB::statement("
            CREATE POLICY phishing_campaigns_insert_policy ON phishing_campaigns
            FOR INSERT
            WITH CHECK (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
            )
        ");
        
        DB::statement('DROP POLICY IF EXISTS phishing_campaigns_update_policy ON phishing_campaigns');
        DB::statement("
            CREATE POLICY phishing_campaigns_update_policy ON phishing_campaigns
            FOR UPDATE
            USING (
                tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
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
        Schema::dropIfExists('phishing_campaigns');
    }
};
