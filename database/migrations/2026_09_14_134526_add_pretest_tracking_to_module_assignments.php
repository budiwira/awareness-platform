<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_assignments', function (Blueprint $table) {
            $table->integer('pretest_score')->nullable()->after('score');
            $table->timestamp('pretest_completed_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('module_assignments', function (Blueprint $table) {
            $table->dropColumn(['pretest_score', 'pretest_completed_at']);
        });
    }
};
