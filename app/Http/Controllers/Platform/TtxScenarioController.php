<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\TtxExercise;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TtxScenarioController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->is_active && $request->user()->isSuperAdmin(), 403);

        return Inertia::render('Platform/Ttx/Scenarios/Index', [
            'scenarios' => TtxExercise::query()
                ->withCount('injects')
                ->orderBy('title')
                ->get(['id', 'tenant_id', 'title', 'scenario', 'objectives', 'capability_codes'])
                ->map(fn (TtxExercise $exercise) => [
                    'id' => $exercise->id,
                    'title' => $exercise->title,
                    'scenario' => $exercise->scenario,
                    'objectives' => $exercise->objectives,
                    'capability_codes' => $exercise->capability_codes,
                    'inject_count' => $exercise->injects_count,
                ])->values(),
        ]);
    }
}
