<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ttx_exercises', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->string('title', 255);
            $table->text('scenario')->nullable();
            $table->text('objectives')->nullable();
            $table->string('scope', 100)->nullable();
            $table->foreignId('playbook_id')->nullable()->constrained('ttx_playbooks')->nullOnDelete();
            $table->foreignId('runbook_id')->nullable()->constrained('ttx_runbooks')->nullOnDelete();
            $table->string('phase', 20)->default('planning'); // planning,preparation,execution,evaluation,completed
            $table->timestamp('scheduled_at')->nullable();
            $table->text('aar_notes')->nullable();
            $table->jsonb('corrective_actions')->nullable();
            $table->timestamps();
        });

        Schema::create('ttx_injects', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('exercise_id')->constrained('ttx_exercises')->cascadeOnDelete();
            $table->integer('order')->default(1);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('ttx_teams', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('exercise_id')->constrained('ttx_exercises')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('ttx_team_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('team_id')->constrained('ttx_teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_in_team', 50)->default('member');
            $table->timestamps();
            $table->unique(['team_id', 'user_id']);
        });

        foreach (['ttx_exercises', 'ttx_injects', 'ttx_teams', 'ttx_team_members'] as $t) {
            DB::statement("ALTER TABLE $t ENABLE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS {$t}_tenant_isolation ON $t");
            DB::statement("CREATE POLICY {$t}_tenant_isolation ON $t FOR ALL USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ttx_team_members');
        Schema::dropIfExists('ttx_teams');
        Schema::dropIfExists('ttx_injects');
        Schema::dropIfExists('ttx_exercises');
    }
};