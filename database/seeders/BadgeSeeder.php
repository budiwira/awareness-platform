<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            [
                'name' => 'First Steps',
                'description' => 'Selesaikan modul pelatihan pertama Anda',
                'icon' => '🎯',
                'category' => 'completion',
                'criteria_type' => 'first_module_completed',
                'criteria_value' => 1,
            ],
            [
                'name' => 'Quiz Master',
                'description' => 'Lulus 5 quiz',
                'icon' => '📚',
                'category' => 'quiz',
                'criteria_type' => 'quiz_passed_count',
                'criteria_value' => 5,
            ],
            [
                'name' => 'Perfect Score',
                'description' => 'Dapatkan nilai 100 pada quiz',
                'icon' => '💯',
                'category' => 'quiz',
                'criteria_type' => 'quiz_perfect_score',
                'criteria_value' => 100,
            ],
            [
                'name' => 'Phishing Defender',
                'description' => 'Tidak pernah mengklik phishing (minimal 3 kampanye)',
                'icon' => '🛡️',
                'category' => 'phishing',
                'criteria_type' => 'phishing_aware',
                'criteria_value' => 3,
            ],
            [
                'name' => '7-Day Streak',
                'description' => 'Login selama 7 hari berturut-turut',
                'icon' => '🔥',
                'category' => 'streak',
                'criteria_type' => 'streak_days',
                'criteria_value' => 7,
            ],
            [
                'name' => '30-Day Streak',
                'description' => 'Login selama 30 hari berturut-turut',
                'icon' => '⚡',
                'category' => 'streak',
                'criteria_type' => 'streak_days',
                'criteria_value' => 30,
            ],
            [
                'name' => '100-Day Streak',
                'description' => 'Login selama 100 hari berturut-turut',
                'icon' => '💎',
                'category' => 'streak',
                'criteria_type' => 'streak_days',
                'criteria_value' => 100,
            ],
            [
                'name' => 'Completionist',
                'description' => 'Selesaikan semua modul yang ditugaskan',
                'icon' => '✅',
                'category' => 'completion',
                'criteria_type' => 'all_modules_completed',
                'criteria_value' => null,
            ],
            [
                'name' => 'CTF Explorer',
                'description' => 'Selesaikan tantangan CTF pertama',
                'icon' => '🏴',
                'category' => 'achievement',
                'criteria_type' => 'first_ctf_solved',
                'criteria_value' => 1,
            ],
            [
                'name' => 'Case Solver',
                'description' => 'Selesaikan case study pertama',
                'icon' => '🔍',
                'category' => 'achievement',
                'criteria_type' => 'first_case_completed',
                'criteria_value' => 1,
            ],
            [
                'name' => 'TTX Participant',
                'description' => 'Ikuti TTX exercise pertama',
                'icon' => '🎮',
                'category' => 'achievement',
                'criteria_type' => 'first_ttx_participated',
                'criteria_value' => 1,
            ],
            [
                'name' => 'High Achiever',
                'description' => 'Raih awareness score 90+',
                'icon' => '🌟',
                'category' => 'achievement',
                'criteria_type' => 'awareness_score_threshold',
                'criteria_value' => 90,
            ],
            [
                'name' => 'Security Champion',
                'description' => 'Raih awareness score 95+',
                'icon' => '👑',
                'category' => 'achievement',
                'criteria_type' => 'awareness_score_threshold',
                'criteria_value' => 95,
            ],
        ];

        foreach ($badges as $badge) {
            Badge::create($badge);
        }
    }
}
