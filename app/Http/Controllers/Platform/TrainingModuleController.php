<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\TrainingModule;
use App\Services\AssessmentAuthoringGuard;
use App\Services\AssessmentLifecycle;
use App\Support\Audit\Audit;
use App\Support\RichContentSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TrainingModuleController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', TrainingModule::class);

        $modules = TrainingModule::withCount('assignments')
            ->with(['pretestQuiz:id,title', 'posttestQuiz:id,title'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($module) {
                return [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'duration_minutes' => $module->duration_minutes,
                    'status' => $module->status,
                    'is_active' => $module->is_active,
                    'assignments_count' => $module->assignments_count,
                    'has_pretest' => $module->pretestQuiz !== null,
                    'has_posttest' => $module->posttestQuiz !== null,
                    'created_at' => $module->created_at,
                ];
            });

        return Inertia::render('Platform/TrainingModules/Index', ['modules' => $modules]);
    }

    public function show(TrainingModule $module)
    {
        Gate::authorize('viewAny', TrainingModule::class);

        $module->load(['pretestQuiz.questions', 'posttestQuiz.questions']);

        $stats = [
            'assignments_count' => $module->assignments()->count(),
            'completed_count' => $module->assignments()->where('status', 'completed')->count(),
            'avg_score' => round(QuizAttempt::whereHas('quiz', fn ($q) => $q->where('training_module_id', $module->id))->where('status', 'submitted')->avg('score') ?? 0),
        ];

        return Inertia::render('Platform/TrainingModules/Show', [
            'module' => $module,
            'stats' => $stats,
            'assessments' => [
                'pretest' => $this->assessmentPayload($module->pretestQuiz, $module),
                'posttest' => $this->assessmentPayload($module->posttestQuiz, $module),
            ],
            'preview' => false,
        ]);
    }

    public function preview(TrainingModule $module)
    {
        Gate::authorize('viewAny', TrainingModule::class);
        $module->load(['pretestQuiz.questions', 'posttestQuiz.questions']);

        return Inertia::render('Platform/TrainingModules/Show', [
            'module' => $module,
            'stats' => [
                'assignments_count' => $module->assignments()->count(),
                'completed_count' => $module->assignments()->where('status', 'completed')->count(),
                'avg_score' => 0,
            ],
            'assessments' => [
                'pretest' => $this->assessmentPayload($module->pretestQuiz, $module),
                'posttest' => $this->assessmentPayload($module->posttestQuiz, $module),
            ],
            'preview' => true,
        ]);
    }

    public function create()
    {
        Gate::authorize('create', TrainingModule::class);

        return Inertia::render('Platform/TrainingModules/Wizard', [
            'module' => null,
            'quizzes' => collect(),
        ]);
    }

    public function edit(TrainingModule $module)
    {
        Gate::authorize('update', $module);

        $module->load(['pretestQuiz.questions', 'posttestQuiz.questions']);

        return Inertia::render('Platform/TrainingModules/Wizard', [
            'module' => $module,
            'quizzes' => Quiz::where('training_module_id', $module->id)
                ->withCount('questions')
                ->orderBy('title')
                ->get()
                ->map(fn (Quiz $quiz): array => array_merge($quiz->toArray(), [
                    'is_frozen' => app(AssessmentAuthoringGuard::class)->isFrozen($quiz),
                    'is_current' => in_array($quiz->id, [$module->pretest_quiz_id, $module->posttest_quiz_id], true),
                ])),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', TrainingModule::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'content_html' => ['required', 'string', 'max:65535'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:draft,published'],
            'passing_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'pretest_quiz_id' => ['prohibited'],
            'posttest_quiz_id' => ['prohibited'],
        ]);

        $cleanHtml = RichContentSanitizer::clean($validated['content_html']);

        $module = TrainingModule::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content_html' => $cleanHtml,
            'content' => trim(strip_tags($cleanHtml)),
            'duration_minutes' => $validated['duration_minutes'],
            'status' => $validated['status'],
        ]);

        Audit::log('module.created', $module, ['title' => $module->title, 'status' => $module->status]);

        return redirect()->route('platform.modules.show', $module)->with('success', 'Modul berhasil dibuat.');
    }

    public function update(Request $request, TrainingModule $module)
    {
        Gate::authorize('update', $module);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'content_html' => ['required', 'string', 'max:65535'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:draft,published,archived'],
            'is_active' => ['required', 'boolean'],
            'pretest_quiz_id' => ['nullable', 'integer', Rule::exists('quizzes', 'id')->where('training_module_id', $module->id)->where('purpose', 'pretest')],
            'posttest_quiz_id' => ['nullable', 'integer', Rule::exists('quizzes', 'id')->where('training_module_id', $module->id)->where('purpose', 'posttest')],
        ]);

        $cleanHtml = RichContentSanitizer::clean($validated['content_html']);
        $validated['content_html'] = $cleanHtml;
        $validated['content'] = trim(strip_tags($cleanHtml));
        [$module, $bindingsChanged] = DB::transaction(function () use ($module, $validated): array {
            $lockedModule = TrainingModule::query()->lockForUpdate()->findOrFail($module->id);
            $oldBindings = [$lockedModule->pretest_quiz_id, $lockedModule->posttest_quiz_id];
            $lockedModule->fill([
                'pretest_quiz_id' => array_key_exists('pretest_quiz_id', $validated)
                    ? $validated['pretest_quiz_id']
                    : $lockedModule->pretest_quiz_id,
                'posttest_quiz_id' => array_key_exists('posttest_quiz_id', $validated)
                    ? $validated['posttest_quiz_id']
                    : $lockedModule->posttest_quiz_id,
            ]);
            $this->lockAssessmentBindings($lockedModule);

            $bindingErrors = app(AssessmentLifecycle::class)->bindingErrors($lockedModule);
            if ($bindingErrors !== []) {
                throw ValidationException::withMessages($bindingErrors);
            }
            if ($validated['status'] === 'published') {
                $this->assertPublishable($lockedModule);
            }

            $lockedModule->update($validated);

            return [
                $lockedModule,
                $oldBindings !== [$lockedModule->pretest_quiz_id, $lockedModule->posttest_quiz_id],
            ];
        });
        Audit::log('module.updated', $module, ['title' => $module->title, 'status' => $module->status]);
        if ($bindingsChanged) {
            Audit::log('quiz.binding_changed', $module, [
                'pretest_quiz_id' => $module->pretest_quiz_id,
                'posttest_quiz_id' => $module->posttest_quiz_id,
            ]);
        }

        return redirect()->route('platform.modules.show', $module)->with('success', 'Modul berhasil diperbarui.');
    }

    public function publish(TrainingModule $module)
    {
        Gate::authorize('update', $module);

        $module = DB::transaction(function () use ($module): TrainingModule {
            $lockedModule = TrainingModule::query()->lockForUpdate()->findOrFail($module->id);
            $this->lockAssessmentBindings($lockedModule);
            $this->assertPublishable($lockedModule);
            $lockedModule->update(['status' => 'published']);

            return $lockedModule;
        });
        Audit::log('module.published', $module, ['title' => $module->title]);

        return back()->with('success', 'Modul berhasil diterbitkan.');
    }

    public function archive(TrainingModule $module)
    {
        Gate::authorize('update', $module);

        $module = DB::transaction(function () use ($module): TrainingModule {
            $lockedModule = TrainingModule::query()->lockForUpdate()->findOrFail($module->id);
            $lockedModule->update(['status' => 'archived', 'is_active' => false]);

            return $lockedModule;
        });
        Audit::log('module.archived', $module, ['title' => $module->title]);

        return back()->with('success', 'Modul berhasil diarsipkan.');
    }

    public function destroy(TrainingModule $module)
    {
        Gate::authorize('delete', $module);

        [$moduleId, $title] = DB::transaction(function () use ($module): array {
            $lockedModule = TrainingModule::query()->lockForUpdate()->findOrFail($module->id);
            if ($lockedModule->assignments()->exists()) {
                throw ValidationException::withMessages([
                    'module' => 'Modul yang memiliki riwayat assignment tidak dapat dihapus. Arsipkan modul untuk mempertahankan riwayat.',
                ]);
            }

            $identity = [$lockedModule->id, $lockedModule->title];
            $lockedModule->delete();

            return $identity;
        });
        Audit::log('module.deleted', null, ['module_id' => $moduleId, 'title' => $title]);

        return redirect()->route('platform.modules.index')->with('success', 'Modul berhasil dihapus.');
    }

    private function assertPublishable(TrainingModule $module): void
    {
        $errors = app(AssessmentLifecycle::class)->bindingErrors($module);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        foreach (['pretest' => $module->pretestQuiz, 'posttest' => $module->posttestQuiz] as $purpose => $quiz) {
            if (! $quiz) {
                continue;
            }

            if (! $quiz->is_active) {
                $errors[$purpose.'_quiz_id'] = ucfirst($purpose).' harus aktif sebelum modul diterbitkan.';

                continue;
            }

            $quiz->loadMissing('questions');
            if ($quiz->questions->isEmpty()) {
                $errors[$purpose.'_quiz_id'] = ucfirst($purpose).' harus memiliki minimal satu pertanyaan valid.';

                continue;
            }

            if ($purpose === 'posttest' && ($quiz->passing_score < 1 || $quiz->passing_score > 100)) {
                $errors['posttest_quiz_id'] = 'Passing score Posttest harus antara 1 dan 100.';

                continue;
            }

            foreach ($quiz->questions as $question) {
                $options = $question->options;
                if (count($options) < 2 || count($options) > 6
                    || collect($options)->contains(fn ($option) => ! is_string($option) || trim($option) === '')
                    || $question->correct_index < 0 || $question->correct_index >= count($options)) {
                    $errors[$purpose.'_quiz_id'] = ucfirst($purpose).' memiliki pertanyaan yang tidak valid.';
                    break;
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function lockAssessmentBindings(TrainingModule $module): void
    {
        $quizIds = collect([$module->pretest_quiz_id, $module->posttest_quiz_id])
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $quizzes = Quiz::query()
            ->whereIn('id', $quizIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->with('questions')
            ->get()
            ->keyBy('id');

        $module->setRelation('pretestQuiz', $module->pretest_quiz_id ? $quizzes->get($module->pretest_quiz_id) : null);
        $module->setRelation('posttestQuiz', $module->posttest_quiz_id ? $quizzes->get($module->posttest_quiz_id) : null);
    }

    /** @return array<string, mixed>|null */
    private function assessmentPayload(?Quiz $quiz, TrainingModule $module): ?array
    {
        if (! $quiz) {
            return null;
        }

        $frozen = app(AssessmentAuthoringGuard::class)->isFrozen($quiz);

        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'purpose' => $quiz->purpose,
            'questions_count' => $quiz->questions->count(),
            'passing_score' => $quiz->purpose === 'posttest' ? $quiz->passing_score : null,
            'duration_minutes' => $quiz->duration_minutes,
            'is_active' => $quiz->is_active,
            'is_frozen' => $frozen,
            'is_mutable' => ! $frozen,
            'is_current' => in_array($quiz->id, [$module->pretest_quiz_id, $module->posttest_quiz_id], true),
        ];
    }
}
