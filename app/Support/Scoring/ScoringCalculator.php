<?php

namespace App\Support\Scoring;

use App\Models\CtfChallenge;
use Illuminate\Support\Collection;

class ScoringCalculator
{
    private const QUALITY_POINTS = ['best' => 100, 'acceptable' => 60, 'poor' => 0];

    /**
     * Hitung skor quiz dari answers vs questions.
     *
     * @return array{score: int, passed: bool}
     */
    public function quiz(Collection $questions, array $answers, int $passingScore): array
    {
        $correct = 0;
        foreach ($questions as $q) {
            $userAnswer = $answers[$q->id] ?? null;
            if ($userAnswer !== null && (int) $userAnswer === (int) $q->correct_option_index) {
                $correct++;
            }
        }

        $score = $questions->count() > 0 ? (int) round($correct / $questions->count() * 100) : 0;

        return ['score' => $score, 'passed' => $score >= $passingScore];
    }

    /**
     * Hitung skor case study dari decisions per scene.
     *
     * @return array{score: int, breakdown: array}
     */
    public function caseStudy(Collection $scenes, array $decisions): array
    {
        $breakdown = [];
        $total = 0;

        foreach ($scenes as $scene) {
            $idx = (int) ($decisions[$scene->id] ?? 0);
            $quality = $scene->options[$idx]['quality'] ?? 'poor';
            $points = self::QUALITY_POINTS[$quality];
            $total += $points;
            $breakdown[$scene->id] = ['quality' => $quality, 'points' => $points];
        }

        $score = $scenes->count() > 0 ? (int) round($total / $scenes->count()) : 0;

        return ['score' => $score, 'breakdown' => $breakdown];
    }

    /**
     * Hitung poin CTF (binary: solved/not).
     */
    public function ctf(CtfChallenge $challenge): int
    {
        return $challenge->points;
    }

    /**
     * Hitung skor TTX dari inject submissions.
     */
    public function ttx(array $injectScores): int
    {
        if (empty($injectScores)) {
            return 0;
        }

        return (int) round(array_sum($injectScores) / count($injectScores));
    }
}
