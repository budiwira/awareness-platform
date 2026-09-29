<?php

namespace App\Services;

use App\Models\Quiz;
use Illuminate\Validation\ValidationException;

class AssessmentAuthoringGuard
{
    public function isFrozen(Quiz $quiz): bool
    {
        return $quiz->attempts()->exists()
            || $quiz->pretestAssignments()->exists()
            || $quiz->posttestAssignments()->exists();
    }

    public function assertMutable(Quiz $quiz): void
    {
        if ($this->isFrozen($quiz)) {
            throw ValidationException::withMessages([
                'quiz' => 'Assessment sudah digunakan dan tidak dapat diubah. Buat assessment pengganti.',
            ]);
        }
    }
}
