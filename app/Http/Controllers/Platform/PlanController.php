<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PlanController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', Plan::class);

        $plans = Plan::withCount('subscriptions')->orderBy('price_monthly')->get();

        return Inertia::render('Platform/Plans/Index', ['plans' => $plans]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Plan::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price_monthly' => ['required', 'integer', 'min:0'],
            'max_users' => ['required', 'integer', 'min:1'],
            'features' => ['nullable', 'string'],
        ]);

        $features = collect(explode("\n", $validated['features'] ?? ''))
            ->map(fn ($s) => trim($s))->filter(fn ($s) => $s !== '')->values()->all();

        $plan = Plan::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'price_monthly' => $validated['price_monthly'],
            'max_users' => $validated['max_users'],
            'features' => $features,
            'is_active' => true,
        ]);

        Audit::log('plan.created', $plan, ['name' => $plan->name]);

        return redirect()->route('platform.plans.index');
    }
}