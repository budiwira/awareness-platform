<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ttx_sessions', function (Blueprint $table) {
            $table->unique(['tenant_id', 'id'], 'ttx_sessions_tenant_id_id_unique');
        });
        Schema::table('ttx_exercises', function (Blueprint $table) {
            $table->unique(['tenant_id', 'id'], 'ttx_exercises_tenant_id_id_unique');
        });
        Schema::table('ttx_injects', function (Blueprint $table) {
            $table->unique(['tenant_id', 'id'], 'ttx_injects_tenant_id_id_unique');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->unique(['tenant_id', 'id'], 'users_tenant_id_id_unique');
        });
        Schema::table('ttx_sessions', function (Blueprint $table) {
            $table->foreign(['tenant_id', 'exercise_id'], 'ttx_sessions_tenant_exercise_fk')
                ->references(['tenant_id', 'id'])->on('ttx_exercises')->restrictOnDelete();
            $table->foreign(['tenant_id', 'created_by'], 'ttx_sessions_tenant_creator_fk')
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });
        Schema::table('ttx_session_participants', function (Blueprint $table) {
            $table->foreign(['tenant_id', 'session_id'], 'ttx_participants_tenant_session_fk')
                ->references(['tenant_id', 'id'])->on('ttx_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'user_id'], 'ttx_participants_tenant_user_fk')
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });
        Schema::table('ttx_session_injects', function (Blueprint $table) {
            $table->foreign(['tenant_id', 'session_id'], 'ttx_injects_tenant_session_fk')
                ->references(['tenant_id', 'id'])->on('ttx_sessions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'inject_id'], 'ttx_injects_tenant_template_fk')
                ->references(['tenant_id', 'id'])->on('ttx_injects')->restrictOnDelete();
            $table->foreign(['tenant_id', 'released_by'], 'ttx_injects_tenant_releaser_fk')
                ->references(['tenant_id', 'id'])->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ttx_session_injects', function (Blueprint $table) {
            $table->dropForeign('ttx_injects_tenant_session_fk');
            $table->dropForeign('ttx_injects_tenant_template_fk');
            $table->dropForeign('ttx_injects_tenant_releaser_fk');
        });
        Schema::table('ttx_sessions', function (Blueprint $table) {
            $table->dropForeign('ttx_sessions_tenant_exercise_fk');
            $table->dropForeign('ttx_sessions_tenant_creator_fk');
        });
        Schema::table('ttx_session_participants', function (Blueprint $table) {
            $table->dropForeign('ttx_participants_tenant_session_fk');
            $table->dropForeign('ttx_participants_tenant_user_fk');
        });
        Schema::table('ttx_sessions', fn (Blueprint $table) => $table->dropUnique('ttx_sessions_tenant_id_id_unique'));
        Schema::table('ttx_injects', fn (Blueprint $table) => $table->dropUnique('ttx_injects_tenant_id_id_unique'));
        Schema::table('ttx_exercises', fn (Blueprint $table) => $table->dropUnique('ttx_exercises_tenant_id_id_unique'));
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique('users_tenant_id_id_unique'));
    }
};
