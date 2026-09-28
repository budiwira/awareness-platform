<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ttx_session_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('session_inject_id');
            $table->text('decision');
            $table->text('rationale')->nullable();
            $table->text('owner')->nullable();
            $table->text('immediate_actions')->nullable();
            $table->text('escalation')->nullable();
            $table->text('unknowns')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at');
            $table->unsignedBigInteger('last_edited_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->unique('session_inject_id', 'ttx_responses_one_per_inject');
        });

        DB::statement('ALTER TABLE ttx_session_responses ADD CONSTRAINT ttx_session_responses_revision_check CHECK (revision >= 1)');

        // Supporting unique for the composite FK: (tenant_id, session_id, session_inject_id).
        Schema::table('ttx_session_injects', function (Blueprint $table) {
            $table->unique(['tenant_id', 'session_id', 'id'], 'ttx_session_injects_tenant_session_id_unique');
        });

        // Composite FK ensuring session_inject belongs to the exact same session.
        Schema::table('ttx_session_responses', function (Blueprint $table) {
            $table->foreign(['tenant_id', 'session_id', 'session_inject_id'], 'ttx_responses_tenant_session_inject_fk')
                ->references(['tenant_id', 'session_id', 'id'])->on('ttx_session_injects')->restrictOnDelete();
            $table->foreign(['tenant_id', 'submitted_by'], 'ttx_responses_tenant_submitter_fk')
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'last_edited_by'], 'ttx_responses_tenant_editor_fk')
                ->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE ttx_session_responses ENABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS ttx_session_responses_tenant_isolation ON ttx_session_responses');
        DB::statement("CREATE POLICY ttx_session_responses_tenant_isolation ON ttx_session_responses FOR ALL USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");
    }

    public function down(): void
    {
        // 1. Drop RLS
        DB::statement('DROP POLICY IF EXISTS ttx_session_responses_tenant_isolation ON ttx_session_responses');
        DB::statement('ALTER TABLE ttx_session_responses DISABLE ROW LEVEL SECURITY');

        // 2. Drop FKs (must precede unique constraint drops they depend on)
        Schema::table('ttx_session_responses', function (Blueprint $table) {
            $table->dropForeign('ttx_responses_tenant_session_inject_fk');
            $table->dropForeign('ttx_responses_tenant_submitter_fk');
            $table->dropForeign('ttx_responses_tenant_editor_fk');
        });

        // 3. Drop unique constraints
        Schema::table('ttx_session_injects', function (Blueprint $table) {
            $table->dropUnique('ttx_session_injects_tenant_session_id_unique');
        });

        DB::statement('DROP INDEX IF EXISTS ttx_responses_one_per_inject');

        // 4. Drop table
        Schema::dropIfExists('ttx_session_responses');
    }
};
