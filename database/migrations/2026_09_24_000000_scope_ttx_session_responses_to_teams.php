<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ttx_session_responses', function (Blueprint $table) {
            $table->unsignedBigInteger('session_team_id')->nullable()->after('session_inject_id');
        });

        // A historical response is attributable only when its session has exactly one team.
        // Ambiguous rows deliberately remain NULL and continue to represent legacy shared data.
        DB::statement(<<<'SQL'
            UPDATE ttx_session_responses AS response
            SET session_team_id = team.id
            FROM (
                SELECT session_id, MIN(id) AS id
                FROM ttx_session_teams
                GROUP BY session_id
                HAVING COUNT(*) = 1
            ) AS team
            WHERE response.session_id = team.session_id
              AND response.session_team_id IS NULL
        SQL);

        Schema::table('ttx_session_responses', function (Blueprint $table) {
            $table->dropUnique('ttx_responses_one_per_inject');
            $table->unique(
                ['session_inject_id', 'session_team_id'],
                'ttx_responses_one_per_inject_team'
            );
            $table->foreign(
                ['tenant_id', 'session_id', 'session_team_id'],
                'ttx_responses_tenant_session_team_fk'
            )->references(['tenant_id', 'session_id', 'id'])
                ->on('ttx_session_teams')
                ->restrictOnDelete();
        });

        DB::statement('DROP POLICY IF EXISTS ttx_session_responses_tenant_isolation ON ttx_session_responses');
        DB::statement('DROP POLICY IF EXISTS ttx_session_responses_select ON ttx_session_responses');
        DB::statement('DROP POLICY IF EXISTS ttx_session_responses_insert ON ttx_session_responses');
        DB::statement('DROP POLICY IF EXISTS ttx_session_responses_update ON ttx_session_responses');
        DB::statement('DROP POLICY IF EXISTS ttx_session_responses_delete ON ttx_session_responses');

        DB::statement(<<<'SQL'
            CREATE POLICY ttx_session_responses_select ON ttx_session_responses
            FOR SELECT USING (
                NULLIF(current_setting('app.role', true), '') = 'super_admin'
                OR (
                    tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                    AND (
                        NULLIF(current_setting('app.role', true), '') = 'tenant_admin'
                        OR EXISTS (
                            SELECT 1
                            FROM ttx_session_participants participant
                            WHERE participant.tenant_id = ttx_session_responses.tenant_id
                              AND participant.session_id = ttx_session_responses.session_id
                              AND participant.team_id = ttx_session_responses.session_team_id
                              AND participant.user_id = NULLIF(current_setting('app.user_id', true), '')::bigint
                        )
                    )
                )
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY ttx_session_responses_insert ON ttx_session_responses
            FOR INSERT WITH CHECK (
                NULLIF(current_setting('app.role', true), '') = 'super_admin'
                OR (
                    tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                    AND submitted_by = NULLIF(current_setting('app.user_id', true), '')::bigint
                    AND session_team_id IS NOT NULL
                    AND EXISTS (
                        SELECT 1
                        FROM ttx_session_participants participant
                        WHERE participant.tenant_id = ttx_session_responses.tenant_id
                          AND participant.session_id = ttx_session_responses.session_id
                          AND participant.team_id = ttx_session_responses.session_team_id
                          AND participant.user_id = NULLIF(current_setting('app.user_id', true), '')::bigint
                    )
                )
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY ttx_session_responses_update ON ttx_session_responses
            FOR UPDATE USING (
                NULLIF(current_setting('app.role', true), '') = 'super_admin'
                OR (
                    tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                    AND (
                        NULLIF(current_setting('app.role', true), '') = 'tenant_admin'
                        OR (
                            session_team_id IS NOT NULL
                            AND EXISTS (
                                SELECT 1
                                FROM ttx_session_participants participant
                                WHERE participant.tenant_id = ttx_session_responses.tenant_id
                                  AND participant.session_id = ttx_session_responses.session_id
                                  AND participant.team_id = ttx_session_responses.session_team_id
                                  AND participant.user_id = NULLIF(current_setting('app.user_id', true), '')::bigint
                            )
                        )
                    )
                )
            ) WITH CHECK (
                NULLIF(current_setting('app.role', true), '') = 'super_admin'
                OR (
                    tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid
                    AND (
                        NULLIF(current_setting('app.role', true), '') = 'tenant_admin'
                        OR (
                            session_team_id IS NOT NULL
                            AND last_edited_by = NULLIF(current_setting('app.user_id', true), '')::bigint
                            AND EXISTS (
                                SELECT 1
                                FROM ttx_session_participants participant
                                WHERE participant.tenant_id = ttx_session_responses.tenant_id
                                  AND participant.session_id = ttx_session_responses.session_id
                                  AND participant.team_id = ttx_session_responses.session_team_id
                                  AND participant.user_id = NULLIF(current_setting('app.user_id', true), '')::bigint
                            )
                        )
                    )
                )
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY ttx_session_responses_delete ON ttx_session_responses
            FOR DELETE USING (NULLIF(current_setting('app.role', true), '') = 'super_admin')
        SQL);

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
        DB::statement('DROP TRIGGER IF EXISTS ttx_response_update_scope ON ttx_session_responses');
        DB::statement('CREATE TRIGGER ttx_response_update_scope BEFORE UPDATE ON ttx_session_responses FOR EACH ROW EXECUTE FUNCTION enforce_ttx_response_update_scope()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS ttx_response_update_scope ON ttx_session_responses');
        DB::statement('DROP FUNCTION IF EXISTS enforce_ttx_response_update_scope()');
        DB::statement('DROP POLICY IF EXISTS ttx_session_responses_select ON ttx_session_responses');
        DB::statement('DROP POLICY IF EXISTS ttx_session_responses_insert ON ttx_session_responses');
        DB::statement('DROP POLICY IF EXISTS ttx_session_responses_update ON ttx_session_responses');
        DB::statement('DROP POLICY IF EXISTS ttx_session_responses_delete ON ttx_session_responses');
        DB::statement("CREATE POLICY ttx_session_responses_tenant_isolation ON ttx_session_responses FOR ALL USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");

        // A downgrade cannot represent more than one team response per inject.
        // Refuse it instead of deleting or silently merging data.
        $duplicates = DB::table('ttx_session_responses')
            ->select('session_inject_id')
            ->groupBy('session_inject_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if ($duplicates) {
            throw new RuntimeException('Cannot downgrade team-scoped responses while an inject has multiple team responses.');
        }

        Schema::table('ttx_session_responses', function (Blueprint $table) {
            $table->dropForeign('ttx_responses_tenant_session_team_fk');
            $table->dropUnique('ttx_responses_one_per_inject_team');
            $table->dropColumn('session_team_id');
            $table->unique('session_inject_id', 'ttx_responses_one_per_inject');
        });
    }
};
