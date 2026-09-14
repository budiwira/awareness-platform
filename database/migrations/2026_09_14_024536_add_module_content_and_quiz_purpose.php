<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->enum('purpose', ['pretest', 'posttest', 'practice'])
                ->default('posttest')
                ->after('training_module_id');
        });

        Schema::table('training_modules', function (Blueprint $table) {
            // tenants.id = UUID, bukan bigint
            $table->uuid('tenant_id')->nullable()->after('id');
            $table->longText('content_html')->nullable()->after('content');

            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });

        Schema::table('training_modules', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn(['tenant_id', 'content_html']);
        });
    }
};
