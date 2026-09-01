<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Facades\DB;

class BadgeAwardService
{
    /**
     * Cek dan award badge jika kriteria terpenuhi.
     */
    public function checkAndAward(User $user, string $criteriaType, ?int $currentValue = null): void
    {
        $badges = Badge::where('criteria_type', $criteriaType)
            ->where('is_active', true)
            ->get();

        foreach ($badges as $badge) {
            // Skip jika user sudah punya badge ini
            if ($this->userHasBadge($user, $badge)) {
                continue;
            }

            // Cek apakah kriteria terpenuhi
            if ($this->criteriaMet($badge, $currentValue)) {
                $this->awardBadge($user, $badge);
            }
        }
    }

    /**
     * Award badge ke user.
     */
    public function awardBadge(User $user, Badge $badge): void
    {
        // Set RLS context untuk insert
        DB::statement("SELECT set_config('app.tenant_id', '{$user->tenant_id}', false)");

        UserBadge::create([
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'tenant_id' => $user->tenant_id,
            'earned_at' => now(),
        ]);
    }

    /**
     * Cek apakah user sudah memiliki badge.
     */
    public function userHasBadge(User $user, Badge $badge): bool
    {
        return UserBadge::where('user_id', $user->id)
            ->where('badge_id', $badge->id)
            ->exists();
    }

    /**
     * Cek apakah kriteria badge terpenuhi.
     */
    private function criteriaMet(Badge $badge, ?int $currentValue): bool
    {
        // Jika badge tidak punya threshold, anggap terpenuhi
        if ($badge->criteria_value === null) {
            return true;
        }

        // Bandingkan dengan threshold
        return $currentValue !== null && $currentValue >= $badge->criteria_value;
    }

    /**
     * Cek badge berdasarkan module completion.
     */
    public function checkModuleCompletion(User $user): void
    {
        $completedCount = $user->moduleAssignments()
            ->where('status', 'completed')
            ->count();

        // First module completed
        if ($completedCount >= 1) {
            $this->checkAndAward($user, 'first_module_completed', 1);
        }

        // All modules completed
        $totalAssigned = $user->moduleAssignments()->count();
        if ($totalAssigned > 0 && $completedCount === $totalAssigned) {
            $this->checkAndAward($user, 'all_modules_completed', 1);
        }
    }

    /**
     * Cek badge berdasarkan quiz performance.
     */
    public function checkQuizPerformance(User $user): void
    {
        // Count passed quizzes
        $passedCount = $user->quizAttempts()
            ->where('passed', true)
            ->distinct('quiz_id')
            ->count('quiz_id');

        $this->checkAndAward($user, 'quiz_passed_count', $passedCount);

        // Perfect score
        $hasPerfectScore = $user->quizAttempts()
            ->where('score', 100)
            ->exists();

        if ($hasPerfectScore) {
            $this->checkAndAward($user, 'quiz_perfect_score', 100);
        }
    }

    /**
     * Cek badge berdasarkan phishing awareness.
     */
    public function checkPhishingAwareness(User $user): void
    {
        $totalCampaigns = $user->phishingTargets()->count();
        $clickedCount = $user->phishingTargets()
            ->where('status', 'clicked')
            ->count();

        // Phishing aware: tidak pernah click dan minimal 3 kampanye
        if ($totalCampaigns >= 3 && $clickedCount === 0) {
            $this->checkAndAward($user, 'phishing_aware', 3);
        }
    }

    /**
     * Cek badge berdasarkan streak.
     */
    public function checkStreak(User $user): void
    {
        $this->checkAndAward($user, 'streak_days', $user->login_streak);
    }

    /**
     * Cek badge berdasarkan awareness score.
     */
    public function checkAwarenessScore(User $user, int $score): void
    {
        $this->checkAndAward($user, 'awareness_score_threshold', $score);
    }

    /**
     * Cek badge achievement lainnya.
     */
    public function checkFirstCTF(User $user): void
    {
        $ctfCount = $user->ctfSolves()->count();
        if ($ctfCount >= 1) {
            $this->checkAndAward($user, 'first_ctf_solved', 1);
        }
    }

    public function checkFirstCase(User $user): void
    {
        $caseCount = $user->caseParticipations()
            ->where('status', 'completed')
            ->count();
        
        if ($caseCount >= 1) {
            $this->checkAndAward($user, 'first_case_completed', 1);
        }
    }

    public function checkFirstTTX(User $user): void
    {
        $ttxCount = $user->ttxScores()->count();
        if ($ttxCount >= 1) {
            $this->checkAndAward($user, 'first_ttx_participated', 1);
        }
    }
}
