<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TtxPlaybook;
use App\Models\TtxRunbook;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TtxController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $playbooks = TtxPlaybook::where('tenant_id', $tenantId)->orderBy('title')->get();
        $runbooks = TtxRunbook::where('tenant_id', $tenantId)->orderBy('title')->get();

        return Inertia::render('Tenant/Ttx/Index', [
            'playbooks' => $playbooks,
            'runbooks' => $runbooks,
        ]);
    }

    public function storePlaybook(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'content' => ['nullable', 'string'],
        ]);

        $playbook = TtxPlaybook::create([
            'tenant_id' => $request->user()->tenant_id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content' => $validated['content'] ?? null,
            'is_active' => true,
        ]);

        Audit::log('ttx.playbook_created', $playbook, ['title' => $playbook->title]);

                return redirect()->route('tenant.ttx.index')->with('success', 'Playbook tersimpan.');
    }

    public function storeRunbook(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'steps' => ['nullable', 'string'],
        ]);

        // Setiap baris = satu langkah
        $steps = collect(explode("\n", $validated['steps'] ?? ''))
            ->map(fn ($s) => trim($s))
            ->filter(fn ($s) => $s !== '')
            ->values()
            ->all();

        $runbook = TtxRunbook::create([
            'tenant_id' => $request->user()->tenant_id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'steps' => $steps,
            'is_active' => true,
        ]);

        Audit::log('ttx.runbook_created', $runbook, ['title' => $runbook->title]);

        return redirect()->route('tenant.ttx.index')->with('success', 'Runbook tersimpan.');
    }
}