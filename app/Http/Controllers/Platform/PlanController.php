<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\TrainingModule;
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

        $plans = Plan::with(['modules:id,title'])->withCount('subscriptions')->orderBy('price_monthly')->get();
        $modules = TrainingModule::where('is_active', true)->where('status', 'published')->orderBy('title')->get(['id', 'title']);

        return Inertia::render('Platform/Plans/Index', [
            'plans' => $plans,
            'modules' => $modules,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Plan::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price_monthly' => ['required', 'integer', 'min:0'],
            'max_users' => ['required', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['in:training,reports_export,ttx,case_studies,ctf'],
            'includes_all_modules' => ['boolean'],
            'module_ids' => ['nullable', 'array'],
            'module_ids.*' => ['exists:training_modules,id'],
        ]);

        $features = $validated['features'] ?? [];
        $includesAll = (bool) ($validated['includes_all_modules'] ?? false);

        $plan = Plan::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'price_monthly' => $validated['price_monthly'],
            'max_users' => $validated['max_users'],
            'features' => $features,
            'includes_all_modules' => $includesAll,
            'is_active' => true,
        ]);

        if (!$includesAll && !empty($validated['module_ids'])) {
            $plan->modules()->attach($validated['module_ids']);
        }

        Audit::log('plan.created', $plan, ['name' => $plan->name]);

        return redirect()->route('platform.plans.index')->with('success', 'Plan baru dibuat.');
    }

    public function update(Request $request, Plan $plan)
    {
        Gate::authorize('update', $plan);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price_monthly' => ['required', 'integer', 'min:0'],
            'max_users' => ['required', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['in:training,reports_export,ttx,case_studies,ctf'],
            'includes_all_modules' => ['boolean'],
            'module_ids' => ['nullable', 'array'],
            'module_ids.*' => ['exists:training_modules,id'],
        ]);

        $features = $validated['features'] ?? [];
        $includesAll = (bool) ($validated['includes_all_modules'] ?? false);

        $plan->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'price_monthly' => $validated['price_monthly'],
            'max_users' => $validated['max_users'],
            'features' => $features,
            'includes_all_modules' => $includesAll,
        ]);

        if ($includesAll) {
            $plan->modules()->detach();
        } else {
            $plan->modules()->sync($validated['module_ids'] ?? []);
        }

        Audit::log('plan.updated', $plan, ['name' => $plan->name]);

        return redirect()->route('platform.plans.index')->with('success', 'Plan berhasil diperbarui.');
    }
}
