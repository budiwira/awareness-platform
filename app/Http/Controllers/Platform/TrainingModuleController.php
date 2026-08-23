<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
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

        $modules = TrainingModule::orderBy('created_at', 'desc')->get();

        return Inertia::render('Platform/TrainingModules/Index', ['modules' => $modules]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', TrainingModule::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
        ]);

        $module = TrainingModule::create($validated);
        Audit::log('module.created', $module, ['title' => $module->title]);

        return redirect()->route('platform.modules.index');
    }

    public function update(Request $request, TrainingModule $module)
    {
        Gate::authorize('update', $module);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ]);

        $module->update($validated);
        Audit::log('module.updated', $module, ['title' => $module->title]);

        return redirect()->route('platform.modules.index');
    }

    public function destroy(TrainingModule $module)
    {
        Gate::authorize('delete', $module);

        $module->delete();
        Audit::log('module.deleted', null, ['title' => $module->title]);

        return redirect()->route('platform.modules.index');
    }
}