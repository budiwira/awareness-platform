<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ttx_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('exercise_id')->constrained('ttx_exercises')->restrictOnDelete();
            $table->string('title', 255);
            $table->string('status', 20)->default('draft');
            $table->jsonb('exercise_snapshot');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('debrief_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('ttx_session_participants', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('session_id')->constrained('ttx_sessions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('session_role', 30);
            $table->timestamps();
            $table->unique(['session_id', 'user_id']);
        });

        Schema::create('ttx_session_injects', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('session_id')->constrained('ttx_sessions')->restrictOnDelete();
            $table->foreignId('inject_id')->constrained('ttx_injects')->restrictOnDelete();
            $table->integer('order')->default(1);
            $table->string('status', 20)->default('pending');
            $table->jsonb('inject_snapshot');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['session_id', 'inject_id']);
        });

        DB::statement("ALTER TABLE ttx_sessions ADD CONSTRAINT ttx_sessions_status_check CHECK (status IN ('draft', 'ready', 'in_progress', 'debrief', 'completed'))");
        DB::statement("ALTER TABLE ttx_session_participants ADD CONSTRAINT ttx_session_participants_role_check CHECK (session_role IN ('facilitator', 'security', 'it_operations', 'people_hr', 'communications', 'management'))");
        DB::statement("ALTER TABLE ttx_session_injects ADD CONSTRAINT ttx_session_injects_status_check CHECK (status IN ('pending', 'active', 'locked'))");
        DB::statement('CREATE UNIQUE INDEX ttx_session_participants_one_facilitator ON ttx_session_participants (session_id) WHERE session_role = \'facilitator\'');
        DB::statement('CREATE UNIQUE INDEX ttx_session_injects_one_active ON ttx_session_injects (session_id) WHERE status = \'active\'');

        foreach (['ttx_sessions', 'ttx_session_participants', 'ttx_session_injects'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("CREATE POLICY {$table}_tenant_isolation ON {$table} FOR ALL USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
        }
    }

    public function down(): void
    {
        foreach (['ttx_sessions', 'ttx_session_participants', 'ttx_session_injects'] as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }

        DB::statement('DROP INDEX IF EXISTS ttx_session_participants_one_facilitator');
        DB::statement('DROP INDEX IF EXISTS ttx_session_injects_one_active');
        Schema::dropIfExists('ttx_session_injects');
        Schema::dropIfExists('ttx_session_participants');
        Schema::dropIfExists('ttx_sessions');
    }
};
