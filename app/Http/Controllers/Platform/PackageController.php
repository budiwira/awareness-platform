<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\TrainingModule;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PackageController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', Package::class);

        $packages = Package::with(['modules:id,title'])->withCount('subscriptions')->orderBy('price_monthly')->get();
        $modules = TrainingModule::where('is_active', true)->where('status', 'published')->orderBy('title')->get(['id', 'title']);

        return Inertia::render('Platform/Packages/Index', [
            'packages' => $packages,
            'modules' => $modules,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Package::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price_monthly' => ['required', 'integer', 'min:0'],
            'max_users' => ['nullable', 'integer', 'min:1'], // nullable for unlimited
            'features' => ['nullable', 'array'],
            'features.*' => ['in:training,reports_export,ttx,case_studies,ctf,phishing'],
            'includes_all_modules' => ['boolean'],
            'module_ids' => ['nullable', 'array'],
            'module_ids.*' => ['exists:training_modules,id'],
        ]);

        $features = $validated['features'] ?? [];
        $includesAll = (bool) ($validated['includes_all_modules'] ?? false);

        $package = Package::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'price_monthly' => $validated['price_monthly'],
            'max_users' => $validated['max_users'] ?? null,
            'features' => $features,
            'includes_all_modules' => $includesAll,
            'is_active' => true,
        ]);

        if (! $includesAll && ! empty($validated['module_ids'])) {
            $package->modules()->attach($validated['module_ids']);
        }

        Audit::log('package.created', $package, ['name' => $package->name]);

        return redirect()->route('platform.packages.index')->with('success', 'Package baru dibuat.');
    }

    public function update(Request $request, Package $package)
    {
        Gate::authorize('update', $package);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price_monthly' => ['required', 'integer', 'min:0'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['in:training,reports_export,ttx,case_studies,ctf,phishing'],
            'includes_all_modules' => ['boolean'],
            'module_ids' => ['nullable', 'array'],
            'module_ids.*' => ['exists:training_modules,id'],
        ]);

        $features = $validated['features'] ?? [];
        $includesAll = (bool) ($validated['includes_all_modules'] ?? false);

        $package->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'price_monthly' => $validated['price_monthly'],
            'max_users' => $validated['max_users'] ?? null,
            'features' => $features,
            'includes_all_modules' => $includesAll,
        ]);

        if ($includesAll) {
            $package->modules()->detach();
        } else {
            $package->modules()->sync($validated['module_ids'] ?? []);
        }

        Audit::log('package.updated', $package, ['name' => $package->name]);

        return redirect()->route('platform.packages.index')->with('success', 'Package berhasil diperbarui.');
    }
}
