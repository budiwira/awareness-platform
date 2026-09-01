<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Users table indexes (new)
        Schema::table('users', function (Blueprint $table) {
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'show_on_leaderboard']);
        });

        // Module assignments indexes (new)
        Schema::table('module_assignments', function (Blueprint $table) {
            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'completed_at']);
        });

        // Quiz attempts indexes (new, skip user_id+quiz_id karena sudah ada)
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'submitted_at']);
        });

        // Phishing targets indexes (new, skip campaign_id+status karena sudah ada)
        Schema::table('phishing_targets', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
            $table->index(['clicked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'is_active']);
            $table->dropIndex(['tenant_id', 'show_on_leaderboard']);
        });

        Schema::table('module_assignments', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropIndex(['tenant_id', 'completed_at']);
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropIndex(['tenant_id', 'submitted_at']);
        });

        Schema::table('phishing_targets', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['clicked_at']);
        });
    }
};
