<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ttx_session_evaluations', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->unsignedBigInteger('session_id');
            $table->string('dimension', 40);
            $table->string('rating', 24);
            $table->text('evidence')->nullable();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['session_id', 'dimension'], 'ttx_evaluations_session_dimension_unique');
            $table->foreign(['tenant_id', 'session_id'], 'ttx_evaluations_tenant_session_fk')
                ->references(['tenant_id', 'id'])->on('ttx_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'updated_by'], 'ttx_evaluations_tenant_updater_fk')
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });

        Schema::create('ttx_action_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->unsignedBigInteger('session_id');
            $table->string('title', 255);
            $table->string('owner', 255);
            $table->string('priority', 16);
            $table->date('due_date')->nullable();
            $table->string('status', 16)->default('open');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'session_id', 'id'], 'ttx_action_items_tenant_session_id_unique');
            $table->foreign(['tenant_id', 'session_id'], 'ttx_action_items_tenant_session_fk')
                ->references(['tenant_id', 'id'])->on('ttx_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'created_by'], 'ttx_action_items_tenant_creator_fk')
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'updated_by'], 'ttx_action_items_tenant_updater_fk')
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });

        Schema::create('ttx_after_action_summaries', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->unsignedBigInteger('session_id');
            $table->text('overall_summary');
            $table->text('strengths');
            $table->text('improvement_areas');
            $table->text('key_lessons');
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique('session_id', 'ttx_aar_session_unique');
            $table->foreign(['tenant_id', 'session_id'], 'ttx_aar_tenant_session_fk')
                ->references(['tenant_id', 'id'])->on('ttx_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'updated_by'], 'ttx_aar_tenant_updater_fk')
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE ttx_session_evaluations ADD CONSTRAINT ttx_evaluations_dimension_check CHECK (dimension IN ('detection_triage', 'escalation_ownership', 'containment_decision', 'cross_functional_coordination', 'incident_communication', 'recovery_improvement'))");
        DB::statement("ALTER TABLE ttx_session_evaluations ADD CONSTRAINT ttx_evaluations_rating_check CHECK (rating IN ('needs_improvement', 'developing', 'effective', 'strong'))");
        DB::statement("ALTER TABLE ttx_action_items ADD CONSTRAINT ttx_action_items_priority_check CHECK (priority IN ('low', 'medium', 'high'))");
        DB::statement("ALTER TABLE ttx_action_items ADD CONSTRAINT ttx_action_items_status_check CHECK (status IN ('open', 'completed'))");
        DB::statement('ALTER TABLE ttx_action_items ADD CONSTRAINT ttx_action_items_title_check CHECK (length(trim(title)) > 0)');
        DB::statement('ALTER TABLE ttx_action_items ADD CONSTRAINT ttx_action_items_owner_check CHECK (length(trim(owner)) > 0)');

        foreach (['ttx_session_evaluations', 'ttx_action_items', 'ttx_after_action_summaries'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("CREATE POLICY {$table}_tenant_isolation ON {$table} FOR ALL USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
        }
    }

    public function down(): void
    {
        foreach (['ttx_after_action_summaries', 'ttx_action_items', 'ttx_session_evaluations'] as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            Schema::dropIfExists($table);
        }
    }
};
