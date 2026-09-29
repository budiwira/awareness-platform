<?php

namespace App\Services;

use App\Models\ModuleAssignment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\TrainingModule;
use Illuminate\Support\Carbon;

class AssessmentLifecycle
{
    public const POSTTEST_MAX_ATTEMPTS = 3;

    public const POSTTEST_COOLDOWN_HOURS = 2;

    public function quiz(ModuleAssignment|TrainingModule $subject, string $purpose): ?Quiz
    {
        if ($subject instanceof ModuleAssignment) {
            $quiz = match ($purpose) {
                'pretest' => $subject->pretestQuiz,
                'posttest' => $subject->posttestQuiz,
                default => null,
            };

            if ($subject->module_snapshot !== null) {
                return $this->isValidBinding($subject->module, $quiz, $purpose) ? $quiz : null;
            }

            return $this->quiz($subject->module, $purpose);
        }

        $quiz = match ($purpose) {
            'pretest' => $subject->pretestQuiz,
            'posttest' => $subject->posttestQuiz,
            default => null,
        };

        if (! $quiz && $purpose === 'posttest' && ! $subject->pretest_quiz_id && ! $subject->posttest_quiz_id) {
            $quiz = $subject->quiz;
        }

        return $this->isValidBinding($subject, $quiz, $purpose) ? $quiz : null;
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
        $pretest = $this->quiz($assignment, 'pretest');
        $posttest = $this->quiz($assignment, 'posttest');
        $configuredInvalid = $this->assignmentBindingErrors($assignment) !== [];
        $pretestAttempt = $pretest ? $this->terminalAttempts($assignment, $pretest)->oldest('submitted_at')->first() : null;
        $posttestAttempts = $posttest ? $this->terminalAttempts($assignment, $posttest)->latest('submitted_at')->get() : collect();
        $latestPosttest = $posttestAttempts->first();
        $cooldownUntil = $latestPosttest && ! $latestPosttest->passed
            ? $latestPosttest->submitted_at?->copy()->addHours(self::POSTTEST_COOLDOWN_HOURS)
            : null;
        $cooldownActive = $cooldownUntil instanceof Carbon && now()->lessThan($cooldownUntil);
        $terminal = in_array($assignment->status, ['completed', 'cancelled'], true);
        $pretestComplete = ! $pretest || $pretestAttempt !== null;
        $contentComplete = $assignment->content_completed_at !== null;

        $stage = match (true) {
            $assignment->status === 'completed' => 'completed',
            $assignment->status === 'cancelled' => 'cancelled',
            $configuredInvalid => 'configuration_unavailable',
            ! $pretestComplete => 'pretest_required',
            ! $contentComplete => 'material',
            $posttest && $posttestAttempts->count() >= self::POSTTEST_MAX_ATTEMPTS => 'attempts_exhausted',
            $posttest && $cooldownActive => 'posttest_cooldown',
            $posttest => 'posttest_available',
            default => 'material',
        };

        return [
            'stage' => $stage,
            'overdue' => $assignment->isOverdue(),
            'configuration_valid' => ! $configuredInvalid,
            'configuration_message' => $configuredInvalid ? 'Assessment assignment belum tersedia karena konfigurasi quiz tidak valid.' : null,
            'can_start_pretest' => ! $terminal && ! $configuredInvalid && $pretest && ! $pretestAttempt,
            'can_start_content' => ! $terminal && ! $configuredInvalid && $pretestComplete && ! $assignment->content_started_at,
            'can_complete_content' => ! $terminal && ! $configuredInvalid && $pretestComplete && ! $contentComplete,
            'can_start_posttest' => ! $terminal && ! $configuredInvalid && $posttest
                && $pretestComplete
                && $contentComplete
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

    public function attempts(ModuleAssignment $assignment, Quiz $quiz)
    {
        return QuizAttempt::query()
            ->where('module_assignment_id', $assignment->id)
            ->where('quiz_id', $quiz->id);
    }

    public function terminalAttempts(ModuleAssignment $assignment, Quiz $quiz)
    {
        return $this->attempts($assignment, $quiz)
            ->whereIn('status', ['submitted', 'expired']);
    }

    /** @return array<string, string> */
    private function assignmentBindingErrors(ModuleAssignment $assignment): array
    {
        $errors = [];
        $module = $assignment->module;

        if ($assignment->pretest_quiz_id && ! $this->isValidBinding($module, $assignment->pretestQuiz, 'pretest')) {
            $errors['pretest_quiz_id'] = 'Pretest assignment tidak valid.';
        }

        if ($assignment->posttest_quiz_id && ! $this->isValidBinding($module, $assignment->posttestQuiz, 'posttest')) {
            $errors['posttest_quiz_id'] = 'Posttest assignment tidak valid.';
        }

        return $errors;
    }
}
