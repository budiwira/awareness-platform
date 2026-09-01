<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description');
            $table->text('icon'); // emoji atau SVG path
            $table->string('category', 50); // completion, quiz, phishing, streak, achievement
            $table->string('criteria_type', 100); // first_quiz_passed, all_modules_completed, etc
            $table->integer('criteria_value')->nullable(); // nilai threshold (7 untuk 7-day streak)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category');
            $table->index('criteria_type');
        });

        // Badges tidak perlu RLS karena global untuk semua tenant
    }

    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
