<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\TrainingModule;
use App\Services\AssessmentAuthoringGuard;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuizController extends Controller
{
    public function __construct(private readonly AssessmentAuthoringGuard $guard) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Quiz::class);

        $quizzes = Quiz::with('module:id,title,pretest_quiz_id,posttest_quiz_id')
            ->withCount('questions')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Quiz $quiz): array => $this->quizPayload($quiz));

        return Inertia::render('Platform/Quizzes/Index', [
            'quizzes' => $quizzes,
            'modules' => TrainingModule::orderBy('title')->get(['id', 'title']),
            'loading' => false,
            'loadError' => null,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Quiz::class);

        $validated = $this->validateQuiz($request, true);
        $bindAsCurrent = (bool) Arr::pull($validated, 'bind_as_current', false);
        $validated['passing_score'] = $validated['purpose'] === 'pretest' ? 1 : $validated['passing_score'];

        $quiz = DB::transaction(function () use ($validated, $bindAsCurrent): Quiz {
            $module = TrainingModule::query()
                ->lockForUpdate()
                ->findOrFail($validated['training_module_id']);
            $quiz = Quiz::create($validated);
            if ($bindAsCurrent && $quiz->purpose !== 'practice') {
                $quiz->setRelation('module', $module);
                $quiz->load('questions');
                if ($module->status === 'published') {
                    $this->assertReadyForPublishedModule($quiz);
                }
                $this->bindCurrent($quiz);
            }

            return $quiz;
        });

        Audit::log('quiz.created', $quiz, ['title' => $quiz->title, 'purpose' => $quiz->purpose]);
        if ($bindAsCurrent && $quiz->purpose !== 'practice') {
            Audit::log('quiz.binding_changed', $quiz, [
                'module_id' => $quiz->training_module_id,
                'purpose' => $quiz->purpose,
            ]);
        }

        return redirect()->route('platform.quizzes.show', $quiz)->with('success', 'Assessment berhasil dibuat.');
    }

    public function show(Quiz $quiz): Response
    {
        return $this->renderQuiz($quiz, false);
    }

    public function preview(Quiz $quiz): Response
    {
        return $this->renderQuiz($quiz, true);
    }

    public function update(Request $request, Quiz $quiz)
    {
        Gate::authorize('update', $quiz);
        $validated = $this->validateQuiz($request, false);
        $validated['passing_score'] = $validated['purpose'] === 'pretest' ? 1 : $validated['passing_score'];

        $quiz = DB::transaction(function () use ($quiz, $validated): Quiz {
            $lockedQuiz = $this->lockQuizForMutation($quiz);
            $this->guard->assertMutable($lockedQuiz);
            if ($this->guard->isCurrent($lockedQuiz) && $validated['purpose'] !== $lockedQuiz->purpose) {
                throw ValidationException::withMessages([
                    'purpose' => 'Purpose assessment aktif tidak dapat diubah. Lepaskan atau ganti binding terlebih dahulu.',
                ]);
            }

            $lockedQuiz->update($validated);
            $this->assertCurrentPublishedQuizRemainsReady($lockedQuiz);

            return $lockedQuiz;
        });
        Audit::log('quiz.updated', $quiz, ['title' => $quiz->title, 'purpose' => $quiz->purpose]);

        return back()->with('success', 'Assessment berhasil diperbarui.');
    }

    public function replace(Quiz $quiz)
    {
        Gate::authorize('create', Quiz::class);
        $replacement = DB::transaction(function () use ($quiz): Quiz {
            $source = $this->lockQuizForMutation($quiz);
            if (! $this->guard->isFrozen($source)) {
                throw ValidationException::withMessages(['quiz' => 'Assessment yang masih mutable dapat diedit langsung.']);
            }

            $source->setRelation('questions', $source->questions()
                ->orderBy('id')
                ->lockForUpdate()
                ->get());
            $replacement = Quiz::create([
                'training_module_id' => $source->training_module_id,
                'purpose' => $source->purpose,
                'title' => Str::limit($source->title, 240, '').' (Pengganti)',
                'passing_score' => $source->purpose === 'pretest' ? 1 : $source->passing_score,
                'duration_minutes' => $source->duration_minutes,
                'is_active' => $source->is_active,
            ]);
            foreach ($source->questions as $question) {
                $replacement->questions()->create($question->only([
                    'question', 'options', 'correct_index', 'explanation',
                ]));
            }

            return $replacement;
        });

        Audit::log('quiz.replacement_created', $replacement, [
            'source_quiz_id' => $quiz->id,
            'purpose' => $quiz->purpose,
        ]);

        return redirect()->route('platform.quizzes.show', $replacement)
            ->with('success', 'Assessment pengganti dibuat. Tinjau sebelum menjadikannya assessment aktif.');
    }

    public function bind(Quiz $quiz)
    {
        Gate::authorize('update', $quiz);
        $quiz = DB::transaction(function () use ($quiz): Quiz {
            $lockedQuiz = $this->lockQuizForMutation($quiz);
            if ($lockedQuiz->purpose === 'practice' || ! $lockedQuiz->is_active) {
                throw ValidationException::withMessages([
                    'quiz' => 'Hanya Pretest atau Posttest aktif yang dapat dijadikan assessment modul.',
                ]);
            }

            $lockedQuiz->loadMissing('questions');
            if ($lockedQuiz->module->status === 'published') {
                $this->assertReadyForPublishedModule($lockedQuiz);
            }
            $this->bindCurrent($lockedQuiz);

            return $lockedQuiz;
        });
        Audit::log('quiz.binding_changed', $quiz, [
            'module_id' => $quiz->training_module_id,
            'purpose' => $quiz->purpose,
        ]);

        return back()->with('success', 'Assessment aktif modul berhasil diperbarui.');
    }

    public function destroy(Quiz $quiz)
    {
        Gate::authorize('delete', $quiz);
        [$quizId, $title] = DB::transaction(function () use ($quiz): array {
            $lockedQuiz = $this->lockQuizForMutation($quiz);
            $this->guard->assertDeletable($lockedQuiz);
            $identity = [$lockedQuiz->id, $lockedQuiz->title];
            $lockedQuiz->delete();

            return $identity;
        });
        Audit::log('quiz.deleted', null, ['quiz_id' => $quizId, 'title' => $title]);

        return redirect()->route('platform.quizzes.index')->with('success', 'Assessment draft berhasil dihapus.');
    }

    public function storeQuestion(Request $request, Quiz $quiz)
    {
        Gate::authorize('update', $quiz);
        $validated = $this->validateQuestion($request);
        $question = DB::transaction(function () use ($quiz, $validated): QuizQuestion {
            $lockedQuiz = $this->lockQuizForMutation($quiz);
            $this->guard->assertMutable($lockedQuiz);
            $question = new QuizQuestion($validated);
            $lockedQuiz->questions()->save($question);
            $this->assertCurrentPublishedQuizRemainsReady($lockedQuiz);

            return $question;
        });
        Audit::log('question.created', $question, ['quiz_id' => $quiz->id]);

        return back()->with('success', 'Pertanyaan berhasil ditambahkan.');
    }

    public function updateQuestion(Request $request, Quiz $quiz, QuizQuestion $question)
    {
        Gate::authorize('update', $quiz);
        $validated = $this->validateQuestion($request);
        $question = DB::transaction(function () use ($quiz, $question, $validated): QuizQuestion {
            $lockedQuiz = $this->lockQuizForMutation($quiz);
            $lockedQuestion = QuizQuestion::query()->lockForUpdate()->findOrFail($question->id);
            $this->assertQuestionParent($lockedQuiz, $lockedQuestion);
            $this->guard->assertMutable($lockedQuiz);
            $lockedQuestion->update($validated);
            $this->assertCurrentPublishedQuizRemainsReady($lockedQuiz);

            return $lockedQuestion;
        });
        Audit::log('question.updated', $question, ['quiz_id' => $quiz->id]);

        return back()->with('success', 'Pertanyaan berhasil diperbarui.');
    }

    public function destroyQuestion(Quiz $quiz, QuizQuestion $question)
    {
        Gate::authorize('update', $quiz);
        $questionId = DB::transaction(function () use ($quiz, $question): int {
            $lockedQuiz = $this->lockQuizForMutation($quiz);
            $lockedQuestion = QuizQuestion::query()->lockForUpdate()->findOrFail($question->id);
            $this->assertQuestionParent($lockedQuiz, $lockedQuestion);
            $this->guard->assertMutable($lockedQuiz);
            $questionId = $lockedQuestion->id;
            $lockedQuestion->delete();
            $this->assertCurrentPublishedQuizRemainsReady($lockedQuiz);

            return $questionId;
        });
        Audit::log('question.deleted', $quiz, ['question_id' => $questionId]);

        return back()->with('success', 'Pertanyaan berhasil dihapus.');
    }

    private function renderQuiz(Quiz $quiz, bool $preview): Response
    {
        Gate::authorize('viewAny', Quiz::class);
        $quiz->load(['module:id,title,pretest_quiz_id,posttest_quiz_id', 'questions']);

        return Inertia::render('Platform/Quizzes/Show', [
            'quiz' => array_merge($quiz->toArray(), $this->quizPayload($quiz)),
            'preview' => $preview,
        ]);
    }

    /** @return array<string, mixed> */
    private function validateQuiz(Request $request, bool $creating): array
    {
        $purpose = $request->input('purpose');
        $passingRules = $purpose === 'pretest'
            ? ['nullable', 'integer', 'min:1', 'max:100']
            : ['required', 'integer', 'min:1', 'max:100'];

        return $request->validate([
            'training_module_id' => $creating
                ? ['required', 'integer', 'exists:training_modules,id']
                : ['prohibited'],
            'purpose' => ['required', 'in:pretest,posttest,practice'],
            'title' => ['required', 'string', 'max:255'],
            'passing_score' => $passingRules,
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'is_active' => ['sometimes', 'boolean'],
            'bind_as_current' => $creating ? ['sometimes', 'boolean'] : ['prohibited'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validateQuestion(Request $request): array
    {
        $validated = $request->validate([
            'quiz_id' => ['prohibited'],
            'question' => ['required', 'string', 'max:2000'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*' => ['required', 'string', 'max:500'],
            'correct_index' => ['required', 'integer', 'min:0'],
            'explanation' => ['nullable', 'string', 'max:4000'],
        ]);

        if ($validated['correct_index'] >= count($validated['options'])) {
            throw ValidationException::withMessages([
                'correct_index' => 'Kunci jawaban harus menunjuk salah satu pilihan yang tersedia.',
            ]);
        }

        return $validated;
    }

    private function assertQuestionParent(Quiz $quiz, QuizQuestion $question): void
    {
        abort_unless($question->quiz_id === $quiz->id, 404);
    }

    private function bindCurrent(Quiz $quiz): void
    {
        $module = $quiz->relationLoaded('module')
            ? $quiz->module
            : TrainingModule::query()->lockForUpdate()->findOrFail($quiz->training_module_id);
        $column = $quiz->purpose === 'pretest' ? 'pretest_quiz_id' : 'posttest_quiz_id';
        $module->update([$column => $quiz->id]);
    }

    private function lockQuizForMutation(Quiz $quiz): Quiz
    {
        $module = TrainingModule::query()->lockForUpdate()->findOrFail($quiz->training_module_id);
        $lockedQuiz = Quiz::query()->lockForUpdate()->findOrFail($quiz->id);
        $lockedQuiz->setRelation('module', $module);

        return $lockedQuiz;
    }

    private function assertCurrentPublishedQuizRemainsReady(Quiz $quiz): void
    {
        if (! $this->guard->isCurrent($quiz) || $quiz->module->status !== 'published') {
            return;
        }

        $quiz->unsetRelation('questions');
        $quiz->load('questions');
        $this->assertReadyForPublishedModule($quiz);
    }

    private function assertReadyForPublishedModule(Quiz $quiz): void
    {
        if (! $quiz->is_active) {
            throw ValidationException::withMessages([
                'quiz' => 'Assessment aktif modul published tidak dapat dinonaktifkan.',
            ]);
        }

        if ($quiz->questions->isEmpty()) {
            throw ValidationException::withMessages([
                'quiz' => 'Assessment harus memiliki minimal satu pertanyaan sebelum diikat ke modul published.',
            ]);
        }

        foreach ($quiz->questions as $question) {
            $options = $question->options;
            if (count($options) < 2 || count($options) > 6
                || collect($options)->contains(fn ($option) => ! is_string($option) || trim($option) === '')
                || $question->correct_index < 0 || $question->correct_index >= count($options)) {
                throw ValidationException::withMessages([
                    'quiz' => 'Assessment memiliki pertanyaan yang belum valid.',
                ]);
            }
        }

        if ($quiz->purpose === 'posttest' && ($quiz->passing_score < 1 || $quiz->passing_score > 100)) {
            throw ValidationException::withMessages(['quiz' => 'Passing score Posttest harus antara 1 dan 100.']);
        }
    }

    /** @return array<string, mixed> */
    private function quizPayload(Quiz $quiz): array
    {
        $quiz->loadMissing('module:id,title,pretest_quiz_id,posttest_quiz_id');
        $questionsCount = $quiz->relationLoaded('questions')
            ? $quiz->questions->count()
            : (int) ($quiz->getAttribute('questions_count') ?? $quiz->questions()->count());
        $frozen = $this->guard->isFrozen($quiz);
        $current = $this->guard->isCurrent($quiz);

        return [
            'id' => $quiz->id,
            'training_module_id' => $quiz->training_module_id,
            'module' => $quiz->module,
            'title' => $quiz->title,
            'purpose' => $quiz->purpose,
            'passing_score' => $quiz->purpose === 'pretest' ? null : $quiz->passing_score,
            'duration_minutes' => $quiz->duration_minutes,
            'is_active' => $quiz->is_active,
            'questions_count' => $questionsCount,
            'is_frozen' => $frozen,
            'is_mutable' => ! $frozen,
            'is_current' => $current,
            'lifecycle_state' => $current ? 'current' : 'historical',
            'created_at' => $quiz->created_at,
        ];
    }
}
