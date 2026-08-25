<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('tenant_id')->index();
            $table->integer('score');
            $table->boolean('passed');
            $table->jsonb('answers')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'quiz_id']);
        });

        DB::statement('ALTER TABLE quiz_attempts ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS quiz_attempts_tenant_isolation ON quiz_attempts');
        DB::statement("CREATE POLICY quiz_attempts_tenant_isolation ON quiz_attempts FOR ALL USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR user_id = NULLIF(current_setting('app.user_id', true), '')::bigint OR NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};