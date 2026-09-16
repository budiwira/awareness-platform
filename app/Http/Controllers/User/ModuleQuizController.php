<?php

namespace App\Http\Controllers\User;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ModuleAssignment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\AssessmentLifecycle;
use App\Services\TenantEntitlement;
use App\Services\UserAccessManager;
use App\Support\Audit\Audit;
use App\Support\Scoring\ScoringCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ModuleQuizController extends Controller
{
    public function __construct(
        private ScoringCalculator $calculator,
        private AssessmentLifecycle $lifecycle,
    ) {}

    public function show(Request $request, ModuleAssignment $assignment)
    {
        $this->ensureOwner($request, $assignment);
        if ($assignment->status === 'completed') {
            return redirect()->route('user.training.show', $assignment)
                ->withErrors(['quiz' => 'Assignment sudah selesai. Hasil sebelumnya tetap dapat ditinjau.']);
        }

        $tenant = $request->user()->tenant;
        $entitlement = app(TenantEntitlement::class);
        if (! $tenant || ! $entitlement->hasModule($tenant, $assignment->training_module_id)) {
            return Inertia::render('Shared/FeatureLocked', [
                'title' => 'Modul Terkunci',
                'message' => 'Organisasi Anda belum mengaktifkan modul ini.',
                'cta' => 'Hubungi admin organisasi',
            ])->toResponse($request)->setStatusCode(403);
        }

        // Per-user access check
        if (! app(UserAccessManager::class)->hasModuleAccessById($request->user(), $assignment->training_module_id)) {
            return Inertia::render('Shared/FeatureLocked', [
                'title' => 'Akses Modul Dibatasi',
                'message' => 'Admin telah membatasi akses Anda ke modul ini.',
                'cta' => 'Hubungi admin organisasi',
            ])->toResponse($request)->setStatusCode(403);
        }

        $quiz = $this->resolveQuiz($request, $assignment);

        if (! $quiz || ! $quiz->is_active) {
            return redirect()->route('user.training.index')->withErrors(['quiz' => 'Quiz belum tersedia untuk modul ini.']);
        }

        $purpose = $this->quizPurpose($assignment, $quiz);
        if ($purpose === 'pretest' && $this->lifecycle->submittedAttempts($quiz, $request->user()->id)->isNotEmpty()) {
            return redirect()->route('user.training.show', $assignment)
                ->withErrors(['quiz' => 'Pretest hanya dapat dikerjakan satu kali.']);
        }

        if ($purpose === 'posttest') {
            $attempts = $this->lifecycle->terminalAttempts($quiz, $request->user()->id);
            if ($attempts->count() >= AssessmentLifecycle::POSTTEST_MAX_ATTEMPTS) {
                return redirect()->route('user.training.show', $assignment)
                    ->withErrors(['quiz' => 'Batas maksimal 3 attempt posttest telah tercapai.']);
            }

            $cooldownUntil = $attempts->first()?->submitted_at?->copy()
                ->addHours(AssessmentLifecycle::POSTTEST_COOLDOWN_HOURS);
            if ($cooldownUntil && now()->lessThan($cooldownUntil)) {
                return redirect()->route('user.training.show', $assignment)
                    ->withErrors(['quiz' => 'Attempt posttest berikutnya tersedia setelah masa tunggu 2 jam.']);
            }
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
                    'purpose' => $this->quizPurpose($assignment, $quiz),
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
                'purpose' => $this->quizPurpose($assignment, $quiz),
                'title' => $quiz->title,
                'passing_score' => $quiz->passing_score,
                'duration_minutes' => $quiz->duration_minutes,
                'question_count' => $quiz->questions->count(),
            ],
            'activeAttempt' => $activeAttempt ? ['id' => $activeAttempt->id] : null,
            'alreadyPassed' => false,
        ]);
    }

    public function start(Request $request, ModuleAssignment $assignment)
    {
        $this->ensureOwner($request, $assignment);

        if ($assignment->status === 'completed') {
            return response()->json(['message' => 'Assignment sudah selesai. Tidak dapat memulai assessment baru.'], 422);
        }

        $tenant = $request->user()->tenant;
        $entitlement = app(TenantEntitlement::class);

        if (! $tenant || ! $entitlement->hasModule($tenant, $assignment->training_module_id)) {
            return response()->json(['message' => 'Organisasi Anda belum mengaktifkan modul ini.'], 403);
        }

        $quiz = $this->resolveQuiz($request, $assignment);

        if (! $quiz || ! $quiz->is_active) {
            return response()->json(['message' => 'Quiz tidak aktif.'], 422);
        }

        $request->validate(['quiz_id' => ['sometimes', 'integer', 'in:'.$quiz->id]]);

        $user = $request->user();

        // Per-user access check: revoked user blocked
        if (! app(UserAccessManager::class)->hasModuleAccessById($user, $quiz->training_module_id)) {
            return response()->json(['message' => 'Akses modul dibatasi oleh admin untuk user ini.'], 403);
        }

        $purpose = $this->quizPurpose($assignment, $quiz);
        if ($purpose === 'pretest' && $this->lifecycle->submittedAttempts($quiz, $user->id)->isNotEmpty()) {
            return response()->json(['message' => 'Pretest hanya dapat dikerjakan satu kali.'], 422);
        }

        if ($purpose === 'posttest') {
            $attempts = $this->lifecycle->terminalAttempts($quiz, $user->id);
            if ($attempts->count() >= AssessmentLifecycle::POSTTEST_MAX_ATTEMPTS) {
                return response()->json(['message' => 'Batas maksimal 3 attempt posttest telah tercapai.'], 422);
            }

            $cooldownUntil = $attempts->first()?->submitted_at?->copy()
                ->addHours(AssessmentLifecycle::POSTTEST_COOLDOWN_HOURS);
            if ($cooldownUntil && now()->lessThan($cooldownUntil)) {
                return response()->json([
                    'message' => 'Attempt posttest berikutnya tersedia setelah masa tunggu 2 jam.',
                    'cooldown_until' => $cooldownUntil->toIso8601String(),
                ], 422);
            }
        }

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
                if ($purpose === 'posttest') {
                    return response()->json([
                        'message' => 'Attempt kedaluwarsa telah diselesaikan. Attempt berikutnya tersedia setelah masa tunggu 2 jam.',
                    ], 422);
                }
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
        $this->ensureAttemptAccess($request, $attempt);
        abort_if($this->assignmentForAttempt($request, $attempt)?->status === 'completed', 422, 'Assignment sudah selesai.');

        return $this->getAttemptPayload($attempt);
    }

    public function submit(Request $request, QuizAttempt $attempt)
    {
        $this->ensureAttemptAccess($request, $attempt);
        abort_if($this->assignmentForAttempt($request, $attempt)?->status === 'completed', 422, 'Assignment sudah selesai.');

        $validated = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        $result = DB::transaction(function () use ($request, $attempt, $validated) {
            $lockedAttempt = QuizAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($lockedAttempt->status !== 'in_progress') {
                throw ValidationException::withMessages(['attempt' => 'Attempt ini sudah diselesaikan.']);
            }

            $quiz = $lockedAttempt->quiz;
            $questions = $quiz->questions->keyBy('id');
            $assignment = ModuleAssignment::where('user_id', $request->user()->id)
                ->where('tenant_id', $request->user()->tenant_id)
                ->where('training_module_id', $quiz->training_module_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($assignment->status === 'completed') {
                throw ValidationException::withMessages(['attempt' => 'Assignment sudah selesai.']);
            }

            $pretest = $this->lifecycle->quiz($assignment->module, 'pretest');
            $posttest = $this->lifecycle->quiz($assignment->module, 'posttest');
            $purpose = match ($quiz->id) {
                $pretest?->id => 'pretest',
                $posttest?->id => 'posttest',
                default => null,
            };

            if (! $purpose) {
                throw ValidationException::withMessages(['attempt' => 'Konfigurasi quiz modul tidak valid.']);
            }

            if ($purpose === 'pretest' && QuizAttempt::where('quiz_id', $quiz->id)
                ->where('user_id', $request->user()->id)
                ->where('status', 'submitted')
                ->whereKeyNot($lockedAttempt->id)
                ->exists()) {
                throw ValidationException::withMessages(['attempt' => 'Pretest hanya dapat diselesaikan satu kali.']);
            }

            $previousPosttestAttempts = $purpose === 'posttest'
                ? QuizAttempt::where('quiz_id', $quiz->id)
                    ->where('user_id', $request->user()->id)
                    ->whereIn('status', ['submitted', 'expired'])
                    ->whereKeyNot($lockedAttempt->id)
                    ->count()
                : 0;

            if ($purpose === 'posttest' && $previousPosttestAttempts >= AssessmentLifecycle::POSTTEST_MAX_ATTEMPTS) {
                throw ValidationException::withMessages(['attempt' => 'Batas maksimal 3 attempt posttest telah tercapai.']);
            }

            $status = $lockedAttempt->deadline_at && now()->greaterThan($lockedAttempt->deadline_at)
                ? 'expired'
                : 'submitted';
            $scoring = $this->calculator->quiz(
                $questions->values(),
                $validated['answers'],
                $lockedAttempt->option_orders ?? [],
                $quiz->passing_score
            );
            $score = $scoring['score'];
            $passed = $scoring['passed'];

            $lockedAttempt->update([
                'status' => $status,
                'submitted_at' => now(),
                'answers' => $validated['answers'],
                'score' => $score,
                'passed' => $passed,
            ]);

            if ($purpose === 'pretest') {
                if ($assignment->pretest_completed_at === null) {
                    $assignment->pretest_score = $score;
                    $assignment->pretest_completed_at = now();
                }
            } else {
                $assignment->score = max((int) ($assignment->score ?? 0), $score);
                if ($passed || $previousPosttestAttempts + 1 >= AssessmentLifecycle::POSTTEST_MAX_ATTEMPTS) {
                    $assignment->status = 'completed';
                    $assignment->completed_at = now();
                }
            }
            $assignment->save();

            Audit::log('quiz.submitted', $lockedAttempt, [
                'score' => $score, 'passed' => $passed, 'status' => $status,
                'quiz_id' => $quiz->id,
                'purpose' => $purpose,
                'module_id' => $quiz->training_module_id,
                'assignment_id' => $assignment->getKey(),
            ]);

            return ['score' => $score, 'passed' => $passed, 'status' => $status];
        });

        return response()->json([
            'attempt_id' => $attempt->id,
            ...$result,
        ]);
    }

    public function result(Request $request, QuizAttempt $attempt)
    {
        if ($attempt->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak berhak melihat hasil ini.');
        }

        $attempt->load('quiz:id,title,passing_score,training_module_id');

        // Cari assignment milik user ini untuk modul yang sama
        $assignment = $request->user()->moduleAssignments()
            ->where('training_module_id', $attempt->quiz->training_module_id)
            ->first(['id', 'status', 'pretest_score', 'score']);

        return Inertia::render('User/MyTraining/Result', [
            'attempt' => $attempt,
            'assignment' => $assignment,
        ]);
    }

    public function review(Request $request, QuizAttempt $attempt)
    {
        // RLS: hanya pemilik attempt
        if ($attempt->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak berhak melihat review ini.');
        }

        // Hanya attempt yang sudah selesai
        if ($attempt->status === 'in_progress') {
            abort(403, 'Review hanya tersedia untuk attempt yang sudah selesai.');
        }

        $quiz = $attempt->quiz;
        $questions = $quiz->questions->keyBy('id');

        // Susun soal sesuai urutan asli (BUKAN question_order)
        $reviewQuestions = [];
        foreach ($questions as $question) {
            $optionOrder = $attempt->option_orders[$question->id] ?? [];

            // Susun opsi sesuai urutan asli (BUKAN teracak)
            $originalOptions = $question->options;

            // Jawaban user (indeks teracak)
            $userShuffledIndex = $attempt->answers[$question->id] ?? null;

            // Map ke indeks asli
            $userOriginalIndex = null;
            if ($userShuffledIndex !== null && isset($optionOrder[$userShuffledIndex])) {
                $userOriginalIndex = $optionOrder[$userShuffledIndex];
            }

            $reviewQuestions[] = [
                'id' => $question->id,
                'question' => $question->question,
                'options' => $originalOptions,
                'user_answer_index' => $userOriginalIndex,
                'correct_index' => $question->correct_index,
                'explanation' => $question->explanation,
            ];
        }

        return Inertia::render('User/MyTraining/Review', [
            'attempt' => [
                'id' => $attempt->id,
                'score' => $attempt->score,
                'passed' => $attempt->passed,
                'status' => $attempt->status,
                'submitted_at' => $attempt->submitted_at,
            ],
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'passing_score' => $quiz->passing_score,
            ],
            'questions' => $reviewQuestions,
        ]);
    }

    private function resolveQuiz(Request $request, ModuleAssignment $assignment): ?Quiz
    {
        $request->validate(['purpose' => ['sometimes', 'in:pretest,posttest']]);
        $purpose = $request->query('purpose');
        $module = $assignment->module;

        $pretest = $this->lifecycle->quiz($module, 'pretest');
        $posttest = $this->lifecycle->quiz($module, 'posttest');
        $pretestCompleted = ! $pretest || $this->lifecycle->submittedAttempts($pretest, $request->user()->id)->isNotEmpty();

        $quiz = match ($purpose) {
            'pretest' => $pretest,
            'posttest' => $posttest,
            default => ! $pretestCompleted ? $pretest : ($posttest ?? $pretest),
        };

        $configuredId = $purpose === 'pretest' ? $module->pretest_quiz_id : $module->posttest_quiz_id;
        abort_if($purpose && $configuredId && ! $quiz, 422, 'Konfigurasi quiz modul tidak valid. Hubungi pengelola konten.');

        if ($quiz) {
            abort_unless($quiz->training_module_id === $module->id, 422, 'Quiz tidak sesuai dengan modul ini.');
            $resolvedPurpose = $this->quizPurpose($assignment, $quiz);
            abort_if($purpose && $resolvedPurpose !== $purpose, 422, 'Purpose quiz tidak sesuai.');
            abort_if($resolvedPurpose && $quiz->purpose !== $resolvedPurpose, 422, 'Purpose quiz tidak sesuai.');
            abort_if($resolvedPurpose === 'posttest' && ! $pretestCompleted, 403, 'Selesaikan pretest terlebih dahulu.');
        }

        return $quiz;
    }

    private function quizPurpose(ModuleAssignment $assignment, Quiz $quiz): ?string
    {
        $pretest = $this->lifecycle->quiz($assignment->module, 'pretest');
        $posttest = $this->lifecycle->quiz($assignment->module, 'posttest');

        return match ($quiz->id) {
            $pretest?->id => 'pretest',
            $posttest?->id => 'posttest',
            default => null,
        };
    }

    private function ensureAttemptAccess(Request $request, QuizAttempt $attempt): void
    {
        $user = $request->user();

        abort_unless($user->role === UserRole::User, 403, 'Hanya learner yang dapat mengakses attempt ini.');
        abort_unless($attempt->user_id === $user->id, 403, 'Anda tidak berhak mengakses attempt ini.');

        $moduleId = $attempt->quiz->training_module_id;
        abort_unless($moduleId !== null, 403, 'Attempt tidak terhubung ke modul.');

        $hasAssignment = $user->moduleAssignments()
            ->where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->where('training_module_id', $moduleId)
            ->exists();

        abort_unless($hasAssignment, 403, 'Anda tidak memiliki assignment untuk modul ini.');
        abort_unless($user->tenant && app(TenantEntitlement::class)->hasModule($user->tenant, $moduleId), 403, 'Organisasi Anda belum mengaktifkan modul ini.');
        abort_unless(app(UserAccessManager::class)->hasModuleAccessById($user, $moduleId), 403, 'Akses modul dibatasi oleh admin.');
    }

    private function ensureOwner(Request $request, ModuleAssignment $assignment): void
    {
        if ($assignment->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak berhak mengakses assignment ini.');
        }
    }

    private function assignmentForAttempt(Request $request, QuizAttempt $attempt): ?ModuleAssignment
    {
        return ModuleAssignment::query()
            ->where('user_id', $request->user()->id)
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('training_module_id', $attempt->quiz->training_module_id)
            ->first();
    }

    private function getAttemptPayload(QuizAttempt $attempt)
    {
        $quiz = $attempt->quiz;
        $questions = $quiz->questions->keyBy('id');

        // Susun soal sesuai urutan teracak
        $shuffledQuestions = [];
        foreach ($attempt->question_order as $questionId) {
            $question = $questions[$questionId] ?? null;
            if (! $question) {
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

        $scoring = $this->calculator->quiz(
            $questions->values(),
            $attempt->answers ?? [],
            $attempt->option_orders ?? [],
            $quiz->passing_score
        );
        $score = $scoring['score'];
        $passed = $scoring['passed'];

        $attempt->update([
            'status' => 'expired',
            'score' => $score,
            'passed' => $passed,
            'submitted_at' => now(),
        ]);

        if ($quiz->purpose === 'posttest') {
            $assignment = ModuleAssignment::where('user_id', $attempt->user_id)
                ->where('training_module_id', $quiz->training_module_id)
                ->first();
            if ($assignment && $assignment->status !== 'completed') {
                $assignment->score = max((int) ($assignment->score ?? 0), $score);
                $attemptCount = $this->lifecycle->terminalAttempts($quiz, $attempt->user_id)->count();
                if ($passed || $attemptCount >= AssessmentLifecycle::POSTTEST_MAX_ATTEMPTS) {
                    $assignment->status = 'completed';
                    $assignment->completed_at = now();
                }
                $assignment->save();
            }
        }

        Audit::log('quiz.expired', $attempt, ['score' => $score]);
    }
}
