<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('id');
            $table->string('role', 20)->default('user')->after('password');
            $table->boolean('is_active')->default(true)->after('role');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->index(['tenant_id', 'role']);
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check
            CHECK (role IN ('super_admin','tenant_admin','user'))");

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_tenant_check
            CHECK ((role = 'super_admin' AND tenant_id IS NULL)
                OR (role IN ('tenant_admin','user') AND tenant_id IS NOT NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_tenant_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'role']);
            $table->dropColumn(['tenant_id', 'role', 'is_active']);
        });
    }
};
