<?php

namespace App\Services;

use App\Models\ModuleAssignment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\TrainingModule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class AssessmentLifecycle
{
    public const POSTTEST_MAX_ATTEMPTS = 3;

    public const POSTTEST_COOLDOWN_HOURS = 2;

    public function quiz(TrainingModule $module, string $purpose): ?Quiz
    {
        $quiz = match ($purpose) {
            'pretest' => $module->pretestQuiz,
            'posttest' => $module->posttestQuiz,
            default => null,
        };

        if (! $quiz && $purpose === 'posttest' && ! $module->pretest_quiz_id && ! $module->posttest_quiz_id) {
            $quiz = $module->quiz;
        }

        return $this->isValidBinding($module, $quiz, $purpose) ? $quiz : null;
    }

    public function isValidBinding(TrainingModule $module, ?Quiz $quiz, string $purpose): bool
    {
        return $quiz instanceof Quiz
            && $quiz->training_module_id === $module->id
            && $quiz->purpose === $purpose;
    }

    /** @return array<string, string> */
    public function bindingErrors(TrainingModule $module): array
    {
        $errors = [];

        if ($module->pretest_quiz_id && ! $this->isValidBinding($module, $module->pretestQuiz, 'pretest')) {
            $errors['pretest_quiz_id'] = 'Pretest harus berupa quiz purpose pretest yang dimiliki modul ini.';
        }

        if ($module->posttest_quiz_id && ! $this->isValidBinding($module, $module->posttestQuiz, 'posttest')) {
            $errors['posttest_quiz_id'] = 'Posttest harus berupa quiz purpose posttest yang dimiliki modul ini.';
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    public function state(ModuleAssignment $assignment): array
    {
        $module = $assignment->module;
        $pretest = $this->quiz($module, 'pretest');
        $posttest = $this->quiz($module, 'posttest');
        $configuredInvalid = $this->bindingErrors($module) !== [];
        $pretestAttempt = $pretest ? $this->submittedAttempts($pretest, $assignment->user_id)->first() : null;
        $posttestAttempts = $posttest ? $this->terminalAttempts($posttest, $assignment->user_id) : collect();
        $latestPosttest = $posttestAttempts->first();
        $cooldownUntil = $latestPosttest?->submitted_at?->copy()->addHours(self::POSTTEST_COOLDOWN_HOURS);
        $cooldownActive = $cooldownUntil instanceof Carbon && now()->lessThan($cooldownUntil);
        $completed = $assignment->status === 'completed';

        $stage = match (true) {
            $completed => 'completed',
            $configuredInvalid => 'configuration_unavailable',
            $pretest && ! $pretestAttempt => 'pretest_required',
            $posttest && $posttestAttempts->count() >= self::POSTTEST_MAX_ATTEMPTS => 'attempts_exhausted',
            $posttest && $cooldownActive => 'posttest_cooldown',
            $posttest => 'posttest_available',
            default => 'material',
        };

        return [
            'stage' => $stage,
            'configuration_valid' => ! $configuredInvalid,
            'configuration_message' => $configuredInvalid ? 'Assessment modul belum tersedia karena konfigurasi quiz tidak valid.' : null,
            'can_start_pretest' => ! $completed && ! $configuredInvalid && $pretest && ! $pretestAttempt,
            'can_start_posttest' => ! $completed && ! $configuredInvalid && $posttest
                && (! $pretest || $pretestAttempt)
                && $posttestAttempts->count() < self::POSTTEST_MAX_ATTEMPTS
                && ! $cooldownActive,
            'posttest_attempts_used' => $posttestAttempts->count(),
            'posttest_attempts_max' => self::POSTTEST_MAX_ATTEMPTS,
            'cooldown_until' => $cooldownUntil?->toIso8601String(),
            'pretest_attempt' => $pretestAttempt,
            'posttest_attempt' => $latestPosttest,
            'pretest_quiz' => $pretest,
            'posttest_quiz' => $posttest,
        ];
    }

    /** @return Collection<int, QuizAttempt> */
    public function submittedAttempts(Quiz $quiz, int $userId): Collection
    {
        return QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $userId)
            ->where('status', 'submitted')
            ->orderBy('submitted_at')
            ->get();
    }

    /** @return Collection<int, QuizAttempt> */
    public function terminalAttempts(Quiz $quiz, int $userId): Collection
    {
        return QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $userId)
            ->whereIn('status', ['submitted', 'expired'])
            ->latest('submitted_at')
            ->get();
    }
}
