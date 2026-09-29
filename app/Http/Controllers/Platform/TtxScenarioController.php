<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Tabletop\ExerciseCapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Models\TtxScenarioInjectTemplate;
use App\Models\TtxScenarioTemplate;
use App\Services\TtxScenarioCatalogService;
use App\Support\Audit\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TtxScenarioController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', TtxScenarioTemplate::class);

        return Inertia::render('Platform/Ttx/Scenarios/Index', [
            'scenarios' => TtxScenarioTemplate::query()
                ->withCount(['injects as active_inject_count' => fn ($query) => $query->where('status', 'active')])
                ->orderByDesc('updated_at')
                ->get(['id', 'title', 'status', 'capability_codes', 'updated_at']),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', TtxScenarioTemplate::class);

        return $this->editor(null);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', TtxScenarioTemplate::class);
        $data = $this->scenarioData($request);
        $template = TtxScenarioTemplate::create([...$data, 'status' => 'draft']);
        Audit::log('ttx.scenario_template_created', $template, ['title' => $template->title]);

        return redirect()->route('platform.ttx.scenarios.edit', $template)->with('success', 'Skenario draft dibuat.');
    }

    public function edit(TtxScenarioTemplate $template): Response
    {
        Gate::authorize('update', $template);

        return $this->editor($template);
    }

    public function update(Request $request, TtxScenarioTemplate $template, TtxScenarioCatalogService $catalog): RedirectResponse
    {
        Gate::authorize('update', $template);
        $data = $this->scenarioData($request);
        DB::transaction(function () use ($template, $data, $catalog) {
            $locked = TtxScenarioTemplate::query()->lockForUpdate()->findOrFail($template->id);
            abort_if($locked->status === 'archived', 422);
            $locked->update($data);
            if ($locked->status === 'published') {
                $catalog->assertPublishable($locked);
            }
            Audit::log('ttx.scenario_template_updated', $locked, ['title' => $locked->title]);
        });

        return back()->with('success', 'Skenario diperbarui.');
    }

    public function publish(TtxScenarioTemplate $template, TtxScenarioCatalogService $catalog): RedirectResponse
    {
        Gate::authorize('update', $template);
        DB::transaction(function () use ($template, $catalog) {
            $locked = TtxScenarioTemplate::query()->lockForUpdate()->findOrFail($template->id);
            abort_if($locked->status === 'archived', 422);
            $catalog->assertPublishable($locked);
            $locked->update(['status' => 'published']);
            Audit::log('ttx.scenario_template_published', $locked, ['title' => $locked->title]);
        });

        return back()->with('success', 'Skenario diterbitkan.');
    }

    public function archive(TtxScenarioTemplate $template): RedirectResponse
    {
        Gate::authorize('update', $template);
        DB::transaction(function () use ($template) {
            $locked = TtxScenarioTemplate::query()->lockForUpdate()->findOrFail($template->id);
            abort_if($locked->status === 'archived', 422);
            $locked->update(['status' => 'archived']);
            Audit::log('ttx.scenario_template_archived', $locked, ['title' => $locked->title]);
        });

        return redirect()->route('platform.ttx.scenarios.index')->with('success', 'Skenario diarsipkan.');
    }

    public function storeInject(Request $request, TtxScenarioTemplate $template, TtxScenarioCatalogService $catalog): RedirectResponse
    {
        Gate::authorize('update', $template);
        $data = $this->injectData($request);
        DB::transaction(function () use ($template, $data, $catalog) {
            $locked = TtxScenarioTemplate::query()->lockForUpdate()->findOrFail($template->id);
            abort_if($locked->status === 'archived', 422);
            $order = (int) $locked->injects()->where('status', 'active')->max('order') + 1;
            $inject = $locked->injects()->create([...$data, 'order' => $order, 'status' => 'active']);
            if ($locked->status === 'published') {
                $catalog->assertPublishable($locked);
            }
            Audit::log('ttx.scenario_inject_created', $inject, ['scenario_template_id' => $locked->id]);
        });

        return back()->with('success', 'Inject ditambahkan.');
    }

    public function updateInject(Request $request, TtxScenarioTemplate $template, TtxScenarioInjectTemplate $inject, TtxScenarioCatalogService $catalog): RedirectResponse
    {
        Gate::authorize('update', $template);
        $data = $this->injectData($request);
        DB::transaction(function () use ($template, $inject, $data, $catalog) {
            $locked = TtxScenarioTemplate::query()->lockForUpdate()->findOrFail($template->id);
            abort_if($locked->status === 'archived', 422);
            $child = $locked->injects()->whereKey($inject->id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $child->update($data);
            if ($locked->status === 'published') {
                $catalog->assertPublishable($locked);
            }
            Audit::log('ttx.scenario_inject_updated', $child, ['scenario_template_id' => $locked->id]);
        });

        return back()->with('success', 'Inject diperbarui.');
    }

    public function archiveInject(TtxScenarioTemplate $template, TtxScenarioInjectTemplate $inject, TtxScenarioCatalogService $catalog): RedirectResponse
    {
        Gate::authorize('update', $template);
        DB::transaction(function () use ($template, $inject, $catalog) {
            $locked = TtxScenarioTemplate::query()->lockForUpdate()->findOrFail($template->id);
            abort_if($locked->status === 'archived', 422);
            $child = $locked->injects()->whereKey($inject->id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $child->update(['status' => 'archived']);
            $catalog->normalizeActiveOrders($locked);
            if ($locked->status === 'published') {
                $catalog->assertPublishable($locked);
            }
            Audit::log('ttx.scenario_inject_archived', $child, ['scenario_template_id' => $locked->id]);
        });

        return back()->with('success', 'Inject diarsipkan.');
    }

    public function reorderInjects(Request $request, TtxScenarioTemplate $template, TtxScenarioCatalogService $catalog): RedirectResponse
    {
        Gate::authorize('update', $template);
        abort_if($template->status === 'archived', 422);
        $data = $request->validate([
            'inject_ids' => ['required', 'array'],
            'inject_ids.*' => ['required', 'integer', 'min:1'],
        ]);
        $catalog->reorder($template, $data['inject_ids']);

        return back()->with('success', 'Urutan inject diperbarui.');
    }

    private function editor(?TtxScenarioTemplate $template): Response
    {
        $template?->load(['injects']);

        return Inertia::render('Platform/Ttx/Scenarios/Editor', [
            'scenario' => $template,
            'capabilities' => ExerciseCapabilityCatalog::catalog(),
        ]);
    }

    private function scenarioData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'scenario' => ['nullable', 'string', 'max:10000'],
            'objectives' => ['nullable', 'string', 'max:5000'],
            'scope' => ['nullable', 'string', 'max:100'],
            'capability_codes' => ['nullable', 'array'],
            'capability_codes.*' => ['string', Rule::in(ExerciseCapabilityCatalog::codes())],
        ]);
        $data['capability_codes'] = ExerciseCapabilityCatalog::normalize($data['capability_codes'] ?? []);

        return $data;
    }

    private function injectData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'capability_codes' => ['nullable', 'array'],
            'capability_codes.*' => ['string', Rule::in(ExerciseCapabilityCatalog::codes())],
        ]);
        $data['capability_codes'] = ExerciseCapabilityCatalog::normalize($data['capability_codes'] ?? []);

        return $data;
    }
}
