<?php

namespace App\Support\Scoring;

use Illuminate\Support\Collection;

class AwarenessScore
{
    // Bobot 5 sinyal — didokumentasikan agar explainable
    public const WEIGHTS = [
        'completion' => 0.25,
        'quiz' => 0.25,
        'case' => 0.15,
        'ctf' => 0.15,
        'ttx' => 0.20,
    ];

    // Pemetaan signal key -> feature key
    public const SIGNAL_FEATURES = [
        'completion' => 'training',
        'quiz' => 'training',
        'case' => 'case_studies',
        'ctf' => 'ctf',
        'ttx' => 'ttx',
    ];

    /**
     * Hitung awareness score yang explainable dari 5 sinyal.
     * $entitledFeatures: array fitur yang aktif untuk tenant (null = full features).
     */
    public function compute(
        Collection $assignments,
        Collection $quizAttempts,
        Collection $caseParticipations,
        Collection $ctfSolves,
        Collection $ttxScores,
        int $totalCtfPoints = 0,
        ?array $entitledFeatures = null
    ): array {
        $completion = $this->completion($assignments);
        $quiz = $this->quizPerformance($quizAttempts);
        $case = $this->casePerformance($caseParticipations);
        $ctf = $this->ctfEngagement($ctfSolves, $totalCtfPoints);
        $ttx = $this->ttxPerformance($ttxScores);

        $scores = [
            'completion' => $completion,
            'quiz' => $quiz,
            'case' => $case,
            'ctf' => $ctf,
            'ttx' => $ttx,
        ];

        // Tentukan sinyal yang ter-entitle
        $entitledSignals = $this->getEntitledSignals($entitledFeatures);
        $lockedSignals = array_diff(array_keys(self::WEIGHTS), $entitledSignals);

        // Hitung bobot ternormalisasi untuk sinyal ter-entitle
        $entitledWeightSum = 0;
        foreach ($entitledSignals as $key) {
            $entitledWeightSum += self::WEIGHTS[$key];
        }

        $overall = 0;
        $breakdown = [];

        foreach (self::WEIGHTS as $key => $weight) {
            $isLocked = in_array($key, $lockedSignals, true);
            
            if ($isLocked) {
                $breakdown[] = [
                    'key' => $key,
                    'label' => $this->labelForKey($key),
                    'score' => (int) round($scores[$key]),
                    'weight' => (int) (self::WEIGHTS[$key] * 100),
                    'locked' => true,
                    'note' => 'Tidak termasuk dalam plan',
                ];
            } else {
                $normalizedWeight = $entitledWeightSum > 0 ? $weight / $entitledWeightSum : 0;
                $overall += $scores[$key] * $normalizedWeight;
                
                $breakdown[] = [
                    'key' => $key,
                    'label' => $this->labelForKey($key),
                    'score' => (int) round($scores[$key]),
                    'weight' => (int) round($normalizedWeight * 100),
                    'locked' => false,
                ];
            }
        }

        return [
            'overall' => (int) round($overall),
            'breakdown' => $breakdown,
        ];
    }

    private function getEntitledSignals(?array $entitledFeatures): array
    {
        if ($entitledFeatures === null) {
            return array_keys(self::WEIGHTS);
        }

        $signals = [];
        foreach (self::SIGNAL_FEATURES as $signal => $feature) {
            if (in_array($feature, $entitledFeatures, true)) {
                $signals[] = $signal;
            }
        }

        return $signals;
    }

    private function labelForKey(string $key): string
    {
        return match ($key) {
            'completion' => 'Training Completion',
            'quiz' => 'Quiz Performance',
            'case' => 'Case Study Performance',
            'ctf' => 'CTF Engagement',
            'ttx' => 'TTX Performance',
        };
    }

    private function completion(Collection $assignments): float
    {
        if ($assignments->count() === 0) {
            return 0;
        }

        $completed = $assignments->where('status', 'completed')->count();

        return ($completed / $assignments->count()) * 100;
    }

    private function quizPerformance(Collection $quizAttempts): float
    {
        $bestPerQuiz = $quizAttempts->groupBy('quiz_id')->map(fn ($g) => $g->max('score'));

        if ($bestPerQuiz->count() === 0) {
            return 0;
        }

        return $bestPerQuiz->avg();
    }

    private function casePerformance(Collection $caseParticipations): float
    {
        $scored = $caseParticipations->whereNotNull('score');

        if ($scored->count() === 0) {
            return 0;
        }

        return $scored->avg('score');
    }

    private function ctfEngagement(Collection $ctfSolves, int $totalCtfPoints): float
    {
        if ($totalCtfPoints <= 0) {
            return 0;
        }

        $earned = $ctfSolves->sum('points');

        return min(100, ($earned / $totalCtfPoints) * 100);
    }

    private function ttxPerformance(Collection $ttxScores): float
    {
        if ($ttxScores->count() === 0) {
            return 0;
        }

        return $ttxScores->avg('score');
    }
}
