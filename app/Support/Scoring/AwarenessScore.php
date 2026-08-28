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

    /**
     * Hitung awareness score yang explainable dari 5 sinyal.
     */
    public function compute(
        Collection $assignments,
        Collection $quizAttempts,
        Collection $caseParticipations,
        Collection $ctfSolves,
        Collection $ttxScores,
        int $totalCtfPoints = 0
    ): array {
        $completion = $this->completion($assignments);
        $quiz = $this->quizPerformance($quizAttempts);
        $case = $this->casePerformance($caseParticipations);
        $ctf = $this->ctfEngagement($ctfSolves, $totalCtfPoints);
        $ttx = $this->ttxPerformance($ttxScores);

        $overall = (int) round(
            $completion * self::WEIGHTS['completion'] +
            $quiz * self::WEIGHTS['quiz'] +
            $case * self::WEIGHTS['case'] +
            $ctf * self::WEIGHTS['ctf'] +
            $ttx * self::WEIGHTS['ttx']
        );

        return [
            'overall' => $overall,
            'breakdown' => [
                $this->row('completion', 'Training Completion', $completion),
                $this->row('quiz', 'Quiz Performance', $quiz),
                $this->row('case', 'Case Study Performance', $case),
                $this->row('ctf', 'CTF Engagement', $ctf),
                $this->row('ttx', 'TTX Performance', $ttx),
            ],
        ];
    }

    private function row(string $key, string $label, float $score): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'score' => (int) round($score),
            'weight' => (int) (self::WEIGHTS[$key] * 100),
        ];
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