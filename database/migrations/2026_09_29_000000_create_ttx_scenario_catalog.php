<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ttx_scenario_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('scenario')->nullable();
            $table->text('objectives')->nullable();
            $table->string('scope', 100)->nullable();
            $table->jsonb('capability_codes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('ttx_scenario_inject_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_template_id')->constrained('ttx_scenario_templates')->restrictOnDelete();
            $table->unsignedInteger('order');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->jsonb('capability_codes')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['scenario_template_id', 'status']);
        });

        Schema::table('ttx_exercises', function (Blueprint $table) {
            $table->foreignId('source_scenario_template_id')->nullable()->index()
                ->constrained('ttx_scenario_templates')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE ttx_scenario_templates ADD CONSTRAINT ttx_scenario_templates_status_check CHECK (status IN ('draft', 'published', 'archived'))");
        DB::statement("ALTER TABLE ttx_scenario_inject_templates ADD CONSTRAINT ttx_scenario_inject_templates_status_check CHECK (status IN ('active', 'archived'))");
        DB::statement('ALTER TABLE ttx_scenario_inject_templates ADD CONSTRAINT ttx_scenario_inject_templates_order_check CHECK ("order" > 0)');
        DB::statement("CREATE UNIQUE INDEX ttx_scenario_inject_templates_active_order_unique ON ttx_scenario_inject_templates (scenario_template_id, \"order\") WHERE status = 'active'");

        DB::statement('ALTER TABLE ttx_scenario_templates ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY ttx_scenario_templates_select ON ttx_scenario_templates
            FOR SELECT USING (
                (NULLIF(current_setting('app.role', true), '') = 'super_admin'
                    AND EXISTS (
                        SELECT 1 FROM users AS actor
                        WHERE actor.id = NULLIF(current_setting('app.user_id', true), '')::bigint
                          AND actor.role = 'super_admin' AND actor.is_active = true AND actor.deleted_at IS NULL
                    ))
                OR (NULLIF(current_setting('app.role', true), '') = 'tenant_admin' AND status = 'published')
            )
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY ttx_scenario_templates_mutate ON ttx_scenario_templates
            FOR ALL USING (
                NULLIF(current_setting('app.role', true), '') = 'super_admin'
                AND EXISTS (SELECT 1 FROM users AS actor
                    WHERE actor.id = NULLIF(current_setting('app.user_id', true), '')::bigint
                      AND actor.role = 'super_admin' AND actor.is_active = true AND actor.deleted_at IS NULL)
            )
            WITH CHECK (
                NULLIF(current_setting('app.role', true), '') = 'super_admin'
                AND EXISTS (SELECT 1 FROM users AS actor
                    WHERE actor.id = NULLIF(current_setting('app.user_id', true), '')::bigint
                      AND actor.role = 'super_admin' AND actor.is_active = true AND actor.deleted_at IS NULL)
            )
        SQL);

        DB::statement('ALTER TABLE ttx_scenario_inject_templates ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY ttx_scenario_inject_templates_select ON ttx_scenario_inject_templates
            FOR SELECT USING (
                (NULLIF(current_setting('app.role', true), '') = 'super_admin'
                    AND EXISTS (
                        SELECT 1 FROM users AS actor
                        WHERE actor.id = NULLIF(current_setting('app.user_id', true), '')::bigint
                          AND actor.role = 'super_admin' AND actor.is_active = true AND actor.deleted_at IS NULL
                    ))
                OR (
                    NULLIF(current_setting('app.role', true), '') = 'tenant_admin'
                    AND status = 'active'
                    AND EXISTS (
                        SELECT 1 FROM ttx_scenario_templates AS parent
                        WHERE parent.id = scenario_template_id AND parent.status = 'published'
                    )
                )
            )
        SQL);
        DB::statement(<<<'SQL'
            CREATE POLICY ttx_scenario_inject_templates_mutate ON ttx_scenario_inject_templates
            FOR ALL USING (
                NULLIF(current_setting('app.role', true), '') = 'super_admin'
                AND EXISTS (SELECT 1 FROM users AS actor
                    WHERE actor.id = NULLIF(current_setting('app.user_id', true), '')::bigint
                      AND actor.role = 'super_admin' AND actor.is_active = true AND actor.deleted_at IS NULL)
            )
            WITH CHECK (
                NULLIF(current_setting('app.role', true), '') = 'super_admin'
                AND EXISTS (SELECT 1 FROM users AS actor
                    WHERE actor.id = NULLIF(current_setting('app.user_id', true), '')::bigint
                      AND actor.role = 'super_admin' AND actor.is_active = true AND actor.deleted_at IS NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::table('ttx_exercises', fn (Blueprint $table) => $table->dropConstrainedForeignId('source_scenario_template_id'));
        Schema::dropIfExists('ttx_scenario_inject_templates');
        Schema::dropIfExists('ttx_scenario_templates');
    }
};
