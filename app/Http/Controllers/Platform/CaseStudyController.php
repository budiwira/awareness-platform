<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\CaseStudy;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CaseStudyController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', CaseStudy::class);

        $cases = CaseStudy::withCount('scenes')->orderBy('created_at', 'desc')->get();

        return Inertia::render('Platform/CaseStudies/Index', ['cases' => $cases]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', CaseStudy::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'difficulty' => ['required', 'in:beginner,intermediate,advanced'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
        ]);

        $case = CaseStudy::create($validated);
        Audit::log('case.created', $case, ['title' => $case->title]);

        return redirect()->route('platform.cases.show', $case);
    }

    public function show(CaseStudy $caseStudy)
    {
        Gate::authorize('viewAny', CaseStudy::class);

        $caseStudy->load('scenes');

        return Inertia::render('Platform/CaseStudies/Show', ['caseStudy' => $caseStudy]);
    }

    public function storeScene(Request $request, CaseStudy $caseStudy)
    {
        Gate::authorize('create', CaseStudy::class);

        $validated = $request->validate([
            'situation' => ['required', 'string', 'max:5000'],
            'options' => ['required', 'array', 'min:2', 'max:4'],
            'options.*.text' => ['required', 'string', 'max:255'],
            'options.*.quality' => ['required', 'in:best,acceptable,poor'],
            'options.*.feedback' => ['required', 'string', 'max:1000'],
        ]);

        $caseStudy->scenes()->create([
            'situation' => $validated['situation'],
            'options' => $validated['options'],
            'order' => $caseStudy->scenes()->count() + 1,
        ]);

        Audit::log('case.scene_added', $caseStudy);

        return redirect()->route('platform.cases.show', $caseStudy);
    }
}