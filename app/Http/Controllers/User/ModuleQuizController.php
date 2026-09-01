<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ModuleAssignment;
use App\Models\QuizAttempt;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ModuleQuizController extends Controller
{
    public function show(Request $request, ModuleAssignment $assignment)
    {
        $this->ensureOwner($request, $assignment);

        $tenant = $request->user()->tenant;
        $entitlement = app(\App\Services\TenantEntitlement::class);
        if (!$tenant || !$entitlement->hasModule($tenant, $assignment->training_module_id)) {
            return \Inertia\Inertia::render('Shared/FeatureLocked', [
                'title' => 'Modul Terkunci',
                'message' => 'Organisasi Anda belum mengaktifkan modul ini.',
                'cta' => 'Hubungi admin organisasi',
            ])->toResponse($request)->setStatusCode(403);
        }

        $quiz = $assignment->module?->quiz;

        if (! $quiz || ! $quiz->is_active) {
            return redirect()->route('user.training.index')->withErrors(['quiz' => 'Quiz belum tersedia untuk modul ini.']);
        }

        // Cek apakah sudah lulus
        $passedAttempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $request->user()->id)
            ->where('passed', true)
            ->first();

        if ($passedAttempt) {
            return Inertia::render('User/MyTraining/Quiz', [
                'assignment' => ['id' => $assignment->id, 'module_title' => $assignment->module->title],
                'quiz' => [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'passing_score' => $quiz->passing_score,
                    'duration_minutes' => $quiz->duration_minutes,
                    'question_count' => $quiz->questions->count(),
                ],
                'alreadyPassed' => true,
                'passedAttempt' => [
                    'id' => $passedAttempt->id,
                    'score' => $passedAttempt->score,
                    'submitted_at' => $passedAttempt->submitted_at,
                ],
            ]);
        }

        // Cek apakah ada attempt in_progress
        $activeAttempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'in_progress')
            ->first();

        if ($activeAttempt) {
            // Cek apakah sudah lewat deadline
            if ($activeAttempt->deadline_at && now()->greaterThan($activeAttempt->deadline_at)) {
                // Finalisasi sebagai expired
                $this->finalizeExpiredAttempt($activeAttempt);
                $activeAttempt = null;
            }
        }

        return Inertia::render('User/MyTraining/Quiz', [
            'assignment' => ['id' => $assignment->id, 'module_title' => $assignment->module->title],
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'passing_score' => $quiz->passing_score,
                'duration_minutes' => $quiz->duration_minutes,
                'question_count' => $quiz->questions->count(),
            ],
            'activeAttempt' => $activeAttempt ? ['id' => $activeAttempt->id] : null,
            'alreadyPassed' => false,
        ]);
    }

    public function start(Request $request)
    {
        $validated = $request->validate([
            'quiz_id' => ['required', 'exists:quizzes,id'],
        ]);

        $quiz = \App\Models\Quiz::findOrFail($validated['quiz_id']);

        if (!$quiz->is_active) {
            return response()->json(['message' => 'Quiz tidak aktif.'], 422);
        }

        $user = $request->user();

        // Cek apakah sudah lulus
        $passedAttempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->where('passed', true)
            ->first();

        if ($passedAttempt) {
            return response()->json(['message' => 'Anda sudah lulus quiz ini.'], 422);
        }

        // Cek attempt in_progress
        $activeAttempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->first();

        if ($activeAttempt) {
            // Cek deadline
            if ($activeAttempt->deadline_at && now()->greaterThan($activeAttempt->deadline_at)) {
                // Finalisasi expired
                $this->finalizeExpiredAttempt($activeAttempt);
            } else {
                // Lanjutkan attempt yang ada
                return $this->getAttemptPayload($activeAttempt);
            }
        }

        // Buat attempt baru
        $questions = $quiz->questions;

        if ($questions->count() === 0) {
            return response()->json(['message' => 'Quiz belum memiliki pertanyaan.'], 422);
        }

        // Acak urutan soal
        $questionOrder = $questions->pluck('id')->shuffle()->values()->toArray();

        // Acak urutan opsi per soal
        $optionOrders = [];
        foreach ($questions as $question) {
            $optionCount = count($question->options);
            $indices = range(0, $optionCount - 1);
            shuffle($indices);
            $optionOrders[$question->id] = $indices;
        }

        $startedAt = now();
        $deadlineAt = $quiz->duration_minutes ? $startedAt->copy()->addMinutes($quiz->duration_minutes) : null;

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'status' => 'in_progress',
            'started_at' => $startedAt,
            'deadline_at' => $deadlineAt,
            'question_order' => $questionOrder,
            'option_orders' => $optionOrders,
        ]);

        Audit::log('quiz.started', $attempt);

        return $this->getAttemptPayload($attempt);
    }

    public function attempt(Request $request, QuizAttempt $attempt)
    {
        if ($attempt->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak berhak mengakses attempt ini.');
        }

        return $this->getAttemptPayload($attempt);
    }

    public function submit(Request $request, QuizAttempt $attempt)
    {
        if ($attempt->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak berhak mengakses attempt ini.');
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json(['message' => 'Attempt ini sudah diselesaikan.'], 422);
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        $quiz = $attempt->quiz;
        $questions = $quiz->questions->keyBy('id');

        // Tentukan status berdasarkan deadline
        $isExpired = $attempt->deadline_at && now()->greaterThan($attempt->deadline_at);
        $status = $isExpired ? 'expired' : 'submitted';

        // Mapping jawaban dari posisi teracak ke indeks asli
        $correct = 0;
        foreach ($questions as $question) {
            $givenShuffledIndex = $validated['answers'][$question->id] ?? null;

            if ($givenShuffledIndex === null) {
                continue;
            }

            $givenShuffledIndex = (int) $givenShuffledIndex;
            $optionOrder = $attempt->option_orders[$question->id] ?? [];

            if (!isset($optionOrder[$givenShuffledIndex])) {
                continue;
            }

            $originalIndex = $optionOrder[$givenShuffledIndex];

            if ($originalIndex === $question->correct_index) {
                $correct++;
            }
        }

        $score = $questions->count() > 0 ? (int) round($correct / $questions->count() * 100) : 0;
        $passed = $score >= $quiz->passing_score;

        $attempt->update([
            'status' => $status,
            'submitted_at' => now(),
            'answers' => $validated['answers'],
            'score' => $score,
            'passed' => $passed,
        ]);

        // Update assignment jika ada
        $assignment = $request->user()->moduleAssignments()
            ->where('training_module_id', $quiz->training_module_id)
            ->first();

        if ($assignment) {
            $assignment->score = max((int) ($assignment->score ?? 0), $score);

            if ($passed && $assignment->status !== 'completed') {
                $assignment->status = 'completed';
                $assignment->completed_at = now();
            }

            $assignment->save();
        }

        Audit::log('quiz.submitted', $attempt, ['score' => $score, 'passed' => $passed, 'status' => $status]);

        return response()->json([
            'attempt_id' => $attempt->id,
            'score' => $score,
            'passed' => $passed,
            'status' => $status,
        ]);
    }

    public function result(Request $request, QuizAttempt $attempt)
    {
        if ($attempt->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak berhak melihat hasil ini.');
        }

        $attempt->load('quiz:id,title,passing_score,training_module_id');

        // Cari assignment milik user ini untuk modul yang sama
        $assignmentId = $request->user()->moduleAssignments()
            ->where('training_module_id', $attempt->quiz->training_module_id)
            ->value('id');

        return Inertia::render('User/MyTraining/Result', [
            'attempt' => $attempt,
            'assignment_id' => $assignmentId,
        ]);
    }

    private function ensureOwner(Request $request, ModuleAssignment $assignment): void
    {
        if ($assignment->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak berhak mengakses assignment ini.');
        }
    }

    private function getAttemptPayload(QuizAttempt $attempt)
    {
        $quiz = $attempt->quiz;
        $questions = $quiz->questions->keyBy('id');

        // Susun soal sesuai urutan teracak
        $shuffledQuestions = [];
        foreach ($attempt->question_order as $questionId) {
            $question = $questions[$questionId] ?? null;
            if (!$question) {
                continue;
            }

            $optionOrder = $attempt->option_orders[$questionId] ?? [];
            $shuffledOptions = [];

            foreach ($optionOrder as $originalIndex) {
                $shuffledOptions[] = $question->options[$originalIndex] ?? null;
            }

            $shuffledQuestions[] = [
                'id' => $question->id,
                'question' => $question->question,
                'options' => array_values(array_filter($shuffledOptions)),
            ];
        }

        return response()->json([
            'attempt_id' => $attempt->id,
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'passing_score' => $quiz->passing_score,
            ],
            'questions' => $shuffledQuestions,
            'deadline_at' => $attempt->deadline_at?->toIso8601String(),
            'started_at' => $attempt->started_at->toIso8601String(),
        ]);
    }

    private function finalizeExpiredAttempt(QuizAttempt $attempt): void
    {
        $quiz = $attempt->quiz;
        $questions = $quiz->questions;

        // Hitung score dari jawaban yang ada (jika ada)
        $correct = 0;
        if ($attempt->answers) {
            foreach ($questions as $question) {
                $givenShuffledIndex = $attempt->answers[$question->id] ?? null;

                if ($givenShuffledIndex === null) {
                    continue;
                }

                $givenShuffledIndex = (int) $givenShuffledIndex;
                $optionOrder = $attempt->option_orders[$question->id] ?? [];

                if (!isset($optionOrder[$givenShuffledIndex])) {
                    continue;
                }

                $originalIndex = $optionOrder[$givenShuffledIndex];

                if ($originalIndex === $question->correct_index) {
                    $correct++;
                }
            }
        }

        $score = $questions->count() > 0 ? (int) round($correct / $questions->count() * 100) : 0;
        $passed = $score >= $quiz->passing_score;

        $attempt->update([
            'status' => 'expired',
            'score' => $score,
            'passed' => $passed,
            'submitted_at' => now(),
        ]);

        Audit::log('quiz.expired', $attempt, ['score' => $score]);
    }
}
