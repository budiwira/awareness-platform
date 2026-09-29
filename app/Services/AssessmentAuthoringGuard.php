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

    public function isCurrent(Quiz $quiz): bool
    {
        return $quiz->module?->pretest_quiz_id === $quiz->id
            || $quiz->module?->posttest_quiz_id === $quiz->id;
    }

    public function assertDeletable(Quiz $quiz): void
    {
        $this->assertMutable($quiz);

        if ($this->isCurrent($quiz)) {
            throw ValidationException::withMessages([
                'quiz' => 'Assessment aktif tidak dapat dihapus. Ganti binding modul terlebih dahulu.',
            ]);
        }
    }
}
