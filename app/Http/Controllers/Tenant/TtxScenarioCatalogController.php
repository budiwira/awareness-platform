<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Tabletop\ExerciseCapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Models\TtxScenarioTemplate;
use App\Services\TenantEntitlement;
use App\Services\TtxScenarioCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TtxScenarioCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeTenant($request);

        return Inertia::render('Tenant/Ttx/Scenarios/Index', [
            'scenarios' => TtxScenarioTemplate::query()
                ->where('status', 'published')
                ->withCount(['injects as active_inject_count' => fn ($query) => $query->where('status', 'active')])
                ->orderBy('title')
                ->get(['id', 'title', 'scenario', 'capability_codes']),
            'capabilities' => ExerciseCapabilityCatalog::catalog(),
        ]);
    }

    public function instantiate(Request $request, TtxScenarioTemplate $template, TtxScenarioCatalogService $catalog): RedirectResponse
    {
        $this->authorizeTenant($request);
        abort_unless($template->status === 'published', 404);
        $exercise = $catalog->instantiate($request->user(), $template);

        return redirect()->route('tenant.ttx.exercises.show', $exercise)->with('success', 'Exercise dari katalog dibuat. Pilih Playbook tenant saat menyiapkan sesi.');
    }

    private function authorizeTenant(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->isTenantAdmin() && $user->tenant_id !== null
            && $user->tenant && app(TenantEntitlement::class)->hasFeature($user->tenant, 'ttx'), 403);
    }
}
