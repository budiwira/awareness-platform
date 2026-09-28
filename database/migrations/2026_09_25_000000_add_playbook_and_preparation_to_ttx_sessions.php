<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ttx_playbooks', function (Blueprint $table) {
            $table->unique(['tenant_id', 'id'], 'ttx_playbooks_tenant_id_id_unique');
        });

        Schema::table('ttx_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('playbook_id')->nullable()->after('exercise_id');
            $table->jsonb('playbook_snapshot')->nullable()->after('exercise_snapshot');
            $table->foreign(['tenant_id', 'playbook_id'], 'ttx_sessions_tenant_playbook_fk')
                ->references(['tenant_id', 'id'])->on('ttx_playbooks')->restrictOnDelete();
        });

        Schema::table('ttx_session_teams', function (Blueprint $table) {
            $table->text('responsibilities')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('ttx_session_teams', function (Blueprint $table) {
            $table->dropColumn('responsibilities');
        });

        Schema::table('ttx_sessions', function (Blueprint $table) {
            $table->dropForeign('ttx_sessions_tenant_playbook_fk');
            $table->dropColumn(['playbook_id', 'playbook_snapshot']);
        });

        Schema::table('ttx_playbooks', function (Blueprint $table) {
            $table->dropUnique('ttx_playbooks_tenant_id_id_unique');
        });
    }
};
