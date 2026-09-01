<?php

namespace App\Observers;

use App\Models\QuizAttempt;
use App\Services\BadgeAwardService;

class QuizAttemptObserver
{
    public function __construct(
        private BadgeAwardService $badgeService
    ) {}

    /**
     * Handle the QuizAttempt "created" event.
     */
    public function created(QuizAttempt $quizAttempt): void
    {
        // Hanya cek badge jika quiz sudah submitted dan passed
        if ($quizAttempt->status === 'submitted' && $quizAttempt->passed) {
            $this->badgeService->checkQuizPerformance($quizAttempt->user);
        }
    }

    /**
     * Handle the QuizAttempt "updated" event.
     */
    public function updated(QuizAttempt $quizAttempt): void
    {
        // Cek badge saat quiz di-submit
        if ($quizAttempt->status === 'submitted' && $quizAttempt->passed) {
            $this->badgeService->checkQuizPerformance($quizAttempt->user);
        }
    }
}
