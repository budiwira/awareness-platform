<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ttx_playbooks', fn (Blueprint $table) => $table->jsonb('structured_phases')->nullable());
        Schema::table('ttx_exercises', fn (Blueprint $table) => $table->jsonb('capability_codes')->nullable());
        Schema::table('ttx_injects', fn (Blueprint $table) => $table->jsonb('capability_codes')->nullable());
        Schema::table('ttx_sessions', fn (Blueprint $table) => $table->unsignedSmallInteger('response_contract_version')->default(1));
        Schema::table('ttx_session_responses', fn (Blueprint $table) => $table->text('coordination_handoff')->nullable());
        Schema::table('ttx_session_evaluations', fn (Blueprint $table) => $table->text('finding')->nullable());
        Schema::table('ttx_action_items', function (Blueprint $table) {
            $table->string('capability_code', 8)->nullable();
            $table->string('playbook_phase_key', 100)->nullable();
            $table->string('category', 32)->nullable();
        });

        DB::statement('ALTER TABLE ttx_sessions ADD CONSTRAINT ttx_sessions_response_contract_check CHECK (response_contract_version IN (1, 2))');
        DB::statement("ALTER TABLE ttx_action_items ADD CONSTRAINT ttx_action_items_capability_check CHECK (capability_code IS NULL OR capability_code IN ('EX-1', 'EX-2', 'EX-3', 'EX-4', 'EX-5', 'EX-6'))");
        DB::statement("ALTER TABLE ttx_action_items ADD CONSTRAINT ttx_action_items_category_check CHECK (category IS NULL OR category IN ('corrective_action', 'playbook_improvement'))");

        Schema::create('ttx_session_responsibility_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('session_team_id');
            $table->string('playbook_phase_key', 100);
            $table->string('role', 16);
            $table->timestamps();

            $table->unique(['session_team_id', 'playbook_phase_key'], 'ttx_responsibility_team_phase_unique');
            $table->foreign(['tenant_id', 'session_id'], 'ttx_responsibility_tenant_session_fk')
                ->references(['tenant_id', 'id'])->on('ttx_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'session_id', 'session_team_id'], 'ttx_responsibility_tenant_session_team_fk')
                ->references(['tenant_id', 'session_id', 'id'])->on('ttx_session_teams')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE ttx_session_responsibility_assignments ADD CONSTRAINT ttx_responsibility_role_check CHECK (role IN ('primary', 'support'))");
        DB::statement("CREATE UNIQUE INDEX ttx_responsibility_one_primary_per_phase ON ttx_session_responsibility_assignments (session_id, playbook_phase_key) WHERE role = 'primary'");
        DB::statement('ALTER TABLE ttx_session_responsibility_assignments ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS ttx_session_responsibility_assignments_select ON ttx_session_responsibility_assignments');
        DB::statement(<<<'SQL'
            CREATE POLICY ttx_session_responsibility_assignments_select ON ttx_session_responsibility_assignments
            FOR SELECT USING (
                NULLIF(current_setting('app.role', true), '') = 'super_admin'
                OR (
                    tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                    AND (
                        NULLIF(current_setting('app.role', true), '') = 'tenant_admin'
                        OR EXISTS (
                            SELECT 1 FROM ttx_session_participants participant
                            WHERE participant.tenant_id = ttx_session_responsibility_assignments.tenant_id
                              AND participant.session_id = ttx_session_responsibility_assignments.session_id
                              AND participant.team_id = ttx_session_responsibility_assignments.session_team_id
                              AND participant.user_id = NULLIF(current_setting('app.user_id', true), '')::bigint
                        )
                    )
                )
            )
        SQL);
        DB::statement('DROP POLICY IF EXISTS ttx_session_responsibility_assignments_mutate ON ttx_session_responsibility_assignments');
        DB::statement(<<<'SQL'
            CREATE POLICY ttx_session_responsibility_assignments_mutate ON ttx_session_responsibility_assignments
            FOR ALL USING (
                NULLIF(current_setting('app.role', true), '') = 'super_admin'
                OR (
                    tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                    AND NULLIF(current_setting('app.role', true), '') = 'tenant_admin'
                    AND EXISTS (
                        SELECT 1 FROM ttx_sessions session
                        WHERE session.tenant_id = ttx_session_responsibility_assignments.tenant_id
                          AND session.id = ttx_session_responsibility_assignments.session_id
                          AND session.created_by = NULLIF(current_setting('app.user_id', true), '')::bigint
                          AND session.status = 'draft'
                    )
                )
            ) WITH CHECK (
                NULLIF(current_setting('app.role', true), '') = 'super_admin'
                OR (
                    tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                    AND NULLIF(current_setting('app.role', true), '') = 'tenant_admin'
                    AND EXISTS (
                        SELECT 1 FROM ttx_sessions session
                        WHERE session.tenant_id = ttx_session_responsibility_assignments.tenant_id
                          AND session.id = ttx_session_responsibility_assignments.session_id
                          AND session.created_by = NULLIF(current_setting('app.user_id', true), '')::bigint
                          AND session.status = 'draft'
                    )
                )
            )
        SQL);

        // Extend the established facilitator immutability trigger to the new response field.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_ttx_response_update_scope()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.tenant_id IS DISTINCT FROM OLD.tenant_id
                    OR NEW.session_id IS DISTINCT FROM OLD.session_id
                    OR NEW.session_inject_id IS DISTINCT FROM OLD.session_inject_id
                    OR NEW.session_team_id IS DISTINCT FROM OLD.session_team_id
                    OR NEW.submitted_by IS DISTINCT FROM OLD.submitted_by
                    OR NEW.submitted_at IS DISTINCT FROM OLD.submitted_at THEN
                    RAISE EXCEPTION 'TTX response ownership is immutable';
                END IF;

                IF NULLIF(current_setting('app.role', true), '') = 'tenant_admin'
                    AND (
                        NEW.decision IS DISTINCT FROM OLD.decision
                        OR NEW.rationale IS DISTINCT FROM OLD.rationale
                        OR NEW.owner IS DISTINCT FROM OLD.owner
                        OR NEW.immediate_actions IS DISTINCT FROM OLD.immediate_actions
                        OR NEW.coordination_handoff IS DISTINCT FROM OLD.coordination_handoff
                        OR NEW.escalation IS DISTINCT FROM OLD.escalation
                        OR NEW.unknowns IS DISTINCT FROM OLD.unknowns
                        OR NEW.notes IS DISTINCT FROM OLD.notes
                        OR NEW.revision IS DISTINCT FROM OLD.revision
                        OR NEW.last_edited_by IS DISTINCT FROM OLD.last_edited_by
                    ) THEN
                    RAISE EXCEPTION 'Facilitators may only lock TTX responses';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS ttx_session_responsibility_assignments_mutate ON ttx_session_responsibility_assignments');
        DB::statement('DROP POLICY IF EXISTS ttx_session_responsibility_assignments_select ON ttx_session_responsibility_assignments');
        DB::statement('DROP INDEX IF EXISTS ttx_responsibility_one_primary_per_phase');
        Schema::dropIfExists('ttx_session_responsibility_assignments');

        DB::statement('ALTER TABLE ttx_action_items DROP CONSTRAINT IF EXISTS ttx_action_items_category_check');
        DB::statement('ALTER TABLE ttx_action_items DROP CONSTRAINT IF EXISTS ttx_action_items_capability_check');
        DB::statement('ALTER TABLE ttx_sessions DROP CONSTRAINT IF EXISTS ttx_sessions_response_contract_check');
        Schema::table('ttx_action_items', fn (Blueprint $table) => $table->dropColumn(['capability_code', 'playbook_phase_key', 'category']));
        Schema::table('ttx_session_evaluations', fn (Blueprint $table) => $table->dropColumn('finding'));
        Schema::table('ttx_session_responses', fn (Blueprint $table) => $table->dropColumn('coordination_handoff'));
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_ttx_response_update_scope()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.tenant_id IS DISTINCT FROM OLD.tenant_id
                    OR NEW.session_id IS DISTINCT FROM OLD.session_id
                    OR NEW.session_inject_id IS DISTINCT FROM OLD.session_inject_id
                    OR NEW.session_team_id IS DISTINCT FROM OLD.session_team_id
                    OR NEW.submitted_by IS DISTINCT FROM OLD.submitted_by
                    OR NEW.submitted_at IS DISTINCT FROM OLD.submitted_at THEN
                    RAISE EXCEPTION 'TTX response ownership is immutable';
                END IF;

                IF NULLIF(current_setting('app.role', true), '') = 'tenant_admin'
                    AND (
                        NEW.decision IS DISTINCT FROM OLD.decision
                        OR NEW.rationale IS DISTINCT FROM OLD.rationale
                        OR NEW.owner IS DISTINCT FROM OLD.owner
                        OR NEW.immediate_actions IS DISTINCT FROM OLD.immediate_actions
                        OR NEW.escalation IS DISTINCT FROM OLD.escalation
                        OR NEW.unknowns IS DISTINCT FROM OLD.unknowns
                        OR NEW.notes IS DISTINCT FROM OLD.notes
                        OR NEW.revision IS DISTINCT FROM OLD.revision
                        OR NEW.last_edited_by IS DISTINCT FROM OLD.last_edited_by
                    ) THEN
                    RAISE EXCEPTION 'Facilitators may only lock TTX responses';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        Schema::table('ttx_sessions', fn (Blueprint $table) => $table->dropColumn('response_contract_version'));
        Schema::table('ttx_injects', fn (Blueprint $table) => $table->dropColumn('capability_codes'));
        Schema::table('ttx_exercises', fn (Blueprint $table) => $table->dropColumn('capability_codes'));
        Schema::table('ttx_playbooks', fn (Blueprint $table) => $table->dropColumn('structured_phases'));
    }
};
