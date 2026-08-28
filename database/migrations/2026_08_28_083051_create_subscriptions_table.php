<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('status', 20)->default('active'); // active, cancelled, past_due
            $table->timestamp('started_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE subscriptions ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS subscriptions_tenant_isolation ON subscriptions');
        DB::statement("CREATE POLICY subscriptions_tenant_isolation ON subscriptions FOR ALL USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};