<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\TrainingModule;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class QuizController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', Quiz::class);

        $quizzes = Quiz::with(['module:id,title', 'questions'])
            ->orderBy('created_at', 'desc')
            ->get();

        $modules = TrainingModule::orderBy('title')->get(['id', 'title']);

        return Inertia::render('Platform/Quizzes/Index', [
            'quizzes' => $quizzes,
            'modules' => $modules,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Quiz::class);

        $validated = $request->validate([
            'training_module_id' => ['required', 'exists:training_modules,id'],
            'title' => ['required', 'string', 'max:255'],
            'passing_score' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $quiz = Quiz::create($validated);
        Audit::log('quiz.created', $quiz, ['title' => $quiz->title]);

        return redirect()->route('platform.quizzes.show', $quiz);
    }

    public function show(Quiz $quiz)
    {
        Gate::authorize('viewAny', Quiz::class);

        $quiz->load(['module:id,title', 'questions']);

        return Inertia::render('Platform/Quizzes/Show', ['quiz' => $quiz]);
    }

    public function storeQuestion(Request $request, Quiz $quiz)
    {
        Gate::authorize('create', Quiz::class);

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*' => ['required', 'string', 'max:255'],
            'correct_index' => ['required', 'integer', 'min:0'],
        ]);

        // Kunci jawaban harus menunjuk pilihan yang benar-benar ada
        if ($validated['correct_index'] >= count($validated['options'])) {
            return redirect()->back()->withErrors(['correct_index' => 'Kunci jawaban melebihi jumlah pilihan.']);
        }

        $quiz->questions()->create($validated);
        Audit::log('quiz.question_added', $quiz);

        return redirect()->route('platform.quizzes.show', $quiz);
    }
}
