<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Models\TrainingModule;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TrainingModuleController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', TrainingModule::class);

        $modules = TrainingModule::withCount('assignments')
            ->with('quiz')
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
                    'has_quiz' => $module->quiz !== null,
                    'created_at' => $module->created_at,
                ];
            });

        return Inertia::render('Platform/TrainingModules/Index', ['modules' => $modules]);
    }

    public function show(TrainingModule $module)
    {
        Gate::authorize('viewAny', TrainingModule::class);

        $module->load(['quiz.questions', 'assignments']);

        $stats = [
            'assignments_count' => $module->assignments()->count(),
            'completed_count' => $module->assignments()->where('status', 'completed')->count(),
            'avg_score' => round(QuizAttempt::whereHas('quiz', fn ($q) => $q->where('training_module_id', $module->id))->where('status', 'submitted')->avg('score') ?? 0),
        ];

        return Inertia::render('Platform/TrainingModules/Show', [
            'module' => $module,
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        Gate::authorize('create', TrainingModule::class);

        return Inertia::render('Platform/TrainingModules/Wizard', [
            'module' => null,
        ]);
    }

    public function edit(TrainingModule $module)
    {
        Gate::authorize('update', $module);

        $module->load('quiz');

        return Inertia::render('Platform/TrainingModules/Wizard', [
            'module' => $module,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', TrainingModule::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:draft,published'],
            'passing_score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $module = TrainingModule::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content' => $validated['content'],
            'duration_minutes' => $validated['duration_minutes'],
            'status' => $validated['status'],
        ]);

        Audit::log('module.created', $module, ['title' => $module->title, 'status' => $module->status]);

        return redirect()->route('platform.modules.show', $module);
    }

    public function update(Request $request, TrainingModule $module)
    {
        Gate::authorize('update', $module);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:draft,published,archived'],
            'is_active' => ['required', 'boolean'],
        ]);

        $module->update($validated);
        Audit::log('module.updated', $module, ['title' => $module->title, 'status' => $module->status]);

        return redirect()->route('platform.modules.show', $module);
    }

    public function publish(TrainingModule $module)
    {
        Gate::authorize('update', $module);

        $module->update(['status' => 'published']);
        Audit::log('module.published', $module, ['title' => $module->title]);

        return back();
    }

    public function archive(TrainingModule $module)
    {
        Gate::authorize('update', $module);

        $module->update(['status' => 'archived']);
        Audit::log('module.archived', $module, ['title' => $module->title]);

        return back();
    }

    public function destroy(TrainingModule $module)
    {
        Gate::authorize('delete', $module);

        $module->delete();
        Audit::log('module.deleted', null, ['title' => $module->title]);

        return redirect()->route('platform.modules.index');
    }
}
