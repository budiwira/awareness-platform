<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TtxExercise;
use App\Models\TtxPlaybook;
use App\Models\TtxRunbook;
use App\Models\TtxTeam;
use App\Models\TtxTeamMember;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TtxExerciseController extends Controller
{
    public const PHASES = [
        'planning' => 'Perencanaan',
        'preparation' => 'Persiapan',
        'execution' => 'Pelaksanaan',
        'evaluation' => 'Evaluasi',
        'completed' => 'Selesai',
    ];

    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $exercises = TtxExercise::with(['playbook:id,title', 'runbook:id,title', 'teams'])
            ->where('tenant_id', $tenantId)
            ->orderBy('scheduled_at')
            ->get();

        $playbooks = TtxPlaybook::where('tenant_id', $tenantId)->where('is_active', true)->get(['id', 'title']);
        $runbooks = TtxRunbook::where('tenant_id', $tenantId)->where('is_active', true)->get(['id', 'title']);

        return Inertia::render('Tenant/Ttx/Exercises/Index', [
            'exercises' => $exercises,
            'playbooks' => $playbooks,
            'runbooks' => $runbooks,
            'phases' => self::PHASES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'scenario' => ['nullable', 'string'],
            'objectives' => ['nullable', 'string'],
            'scope' => ['nullable', 'string', 'max:100'],
            'playbook_id' => ['nullable', 'exists:ttx_playbooks,id'],
            'runbook_id' => ['nullable', 'exists:ttx_runbooks,id'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $exercise = TtxExercise::create([
            'tenant_id' => $request->user()->tenant_id,
            'title' => $validated['title'],
            'scenario' => $validated['scenario'] ?? null,
            'objectives' => $validated['objectives'] ?? null,
            'scope' => $validated['scope'] ?? null,
            'playbook_id' => $validated['playbook_id'] ?? null,
            'runbook_id' => $validated['runbook_id'] ?? null,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'phase' => 'planning',
        ]);

        Audit::log('ttx.exercise_created', $exercise, ['title' => $exercise->title]);

        return redirect()->route('tenant.ttx.exercises.show', $exercise);
    }

    public function show(Request $request, TtxExercise $exercise)
    {
        $this->ensureTenant($request, $exercise->tenant_id);

        $exercise->load(['playbook:id,title', 'runbook:id,title', 'injects', 'teams.members.user:id,name,email']);

        $users = User::where('tenant_id', $request->user()->tenant_id)->get(['id', 'name', 'email']);

        return Inertia::render('Tenant/Ttx/Exercises/Show', [
            'exercise' => $exercise,
            'users' => $users,
            'phases' => self::PHASES,
        ]);
    }

    public function storeTeam(Request $request, TtxExercise $exercise)
    {
        $this->ensureTenant($request, $exercise->tenant_id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        TtxTeam::create([
            'tenant_id' => $request->user()->tenant_id,
            'exercise_id' => $exercise->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        Audit::log('ttx.team_created', $exercise, ['team' => $validated['name']]);

        return redirect()->route('tenant.ttx.exercises.show', $exercise);
    }

    public function storeTeamMember(Request $request, TtxTeam $team)
    {
        $this->ensureTenant($request, $team->tenant_id);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role_in_team' => ['required', 'in:lead,member'],
        ]);

        // Defense in depth: anggota harus dari tenant yang sama
        $member = User::where('id', $validated['user_id'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->first();

        if (! $member) {
            abort(403, 'User bukan anggota tenant Anda.');
        }

        TtxTeamMember::updateOrCreate(
            ['team_id' => $team->id, 'user_id' => $member->id],
            ['tenant_id' => $request->user()->tenant_id, 'role_in_team' => $validated['role_in_team']]
        );

        Audit::log('ttx.member_added', $team, ['user_id' => $member->id]);

        return redirect()->back();
    }

    private function ensureTenant(Request $request, string $tenantId): void
    {
        if ($request->user()->tenant_id !== $tenantId) {
            abort(403, 'Anda tidak berhak mengakses exercise ini.');
        }
    }
}