<?php

namespace App\Support\Scoring;

use App\Models\CtfChallenge;
use Illuminate\Support\Collection;

class ScoringCalculator
{
    private const QUALITY_POINTS = ['best' => 100, 'acceptable' => 60, 'poor' => 0];

    /**
     * Hitung skor quiz dengan semantik shuffled options.
     *
     * @param  Collection  $questions  pertanyaan dengan properti correct_index
     * @param  array  $answers  map question_id => shuffled index pilihan user
     * @param  array  $optionOrders  map question_id => [shuffled_idx => original_idx]
     * @return array{score: int, passed: bool}
     */
    public function quiz(Collection $questions, array $answers, array $optionOrders, int $passingScore): array
    {
        $correct = 0;

        foreach ($questions as $question) {
            $givenShuffledIndex = $answers[$question->id] ?? null;

            if ($givenShuffledIndex === null) {
                continue;
            }

            $givenShuffledIndex = (int) $givenShuffledIndex;
            $optionOrder = $optionOrders[$question->id] ?? [];

            if (! isset($optionOrder[$givenShuffledIndex])) {
                continue;
            }

            $originalIndex = $optionOrder[$givenShuffledIndex];

            if ($originalIndex === $question->correct_index) {
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

    public function ctf(CtfChallenge $challenge): int
    {
        return $challenge->points;
    }

    public function ttx(array $injectScores): int
    {
        if (empty($injectScores)) {
            return 0;
        }

        return (int) round(array_sum($injectScores) / count($injectScores));
    }
}
