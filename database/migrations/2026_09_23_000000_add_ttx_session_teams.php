<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ttx_session_teams', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->unsignedBigInteger('session_id');
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'session_id', 'id'], 'ttx_session_teams_tenant_session_id_unique');
            $table->unique(['session_id', 'name'], 'ttx_session_teams_session_name_unique');
            $table->foreign(['tenant_id', 'session_id'], 'ttx_session_teams_tenant_session_fk')
                ->references(['tenant_id', 'id'])->on('ttx_sessions')->restrictOnDelete();
        });

        Schema::table('ttx_session_participants', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('user_id');
            $table->foreign(['tenant_id', 'session_id', 'team_id'], 'ttx_participants_tenant_session_team_fk')
                ->references(['tenant_id', 'session_id', 'id'])->on('ttx_session_teams')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE ttx_session_participants DROP CONSTRAINT IF EXISTS ttx_session_participants_role_check');
        DB::statement('ALTER TABLE ttx_session_participants ALTER COLUMN session_role DROP NOT NULL');

        DB::statement('ALTER TABLE ttx_session_teams ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS ttx_session_teams_tenant_isolation ON ttx_session_teams');
        DB::statement("CREATE POLICY ttx_session_teams_tenant_isolation ON ttx_session_teams FOR ALL USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        DB::statement("UPDATE ttx_session_participants SET session_role = 'management' WHERE session_role IS NULL");
        DB::statement('ALTER TABLE ttx_session_participants ALTER COLUMN session_role SET NOT NULL');
        DB::statement("ALTER TABLE ttx_session_participants ADD CONSTRAINT ttx_session_participants_role_check CHECK (session_role IN ('facilitator', 'security', 'it_operations', 'people_hr', 'communications', 'management'))");

        Schema::table('ttx_session_participants', function (Blueprint $table) {
            $table->dropForeign('ttx_participants_tenant_session_team_fk');
            $table->dropColumn('team_id');
        });

        DB::statement('DROP POLICY IF EXISTS ttx_session_teams_tenant_isolation ON ttx_session_teams');
        DB::statement('ALTER TABLE ttx_session_teams DISABLE ROW LEVEL SECURITY');
        Schema::dropIfExists('ttx_session_teams');
    }
};
