<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TtxActionItem;
use App\Models\TtxExercise;
use App\Models\TtxPlaybook;
use App\Models\TtxSession;
use App\Models\TtxSessionResponse;
use App\Models\TtxSessionTeam;
use App\Models\User;
use App\Services\TenantEntitlement;
use App\Services\TtxSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TtxSessionController extends Controller
{
    public function store(Request $request, TtxExercise $exercise, TtxSessionService $service)
    {
        Gate::authorize('create', TtxSession::class);
        abort_unless($exercise->tenant_id === $request->user()->tenant_id, 403);
        abort_unless($request->user()->tenant && app(TenantEntitlement::class)->hasFeature($request->user()->tenant, 'ttx'), 403);
        $data = $request->validate([
            'playbook_id' => ['required', 'integer', Rule::exists('ttx_playbooks', 'id')->where('tenant_id', $request->user()->tenant_id)->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
        ]);
        $playbook = TtxPlaybook::query()->where('tenant_id', $request->user()->tenant_id)->findOrFail($data['playbook_id']);
        $session = $service->create($request->user(), $exercise, $data['title'], isset($data['scheduled_at']) ? new \DateTimeImmutable($data['scheduled_at']) : null, $playbook);

        return redirect()->route('tenant.ttx.sessions.prepare', $session);
    }

    public function index(Request $request, TtxSessionService $service)
    {
        Gate::authorize('viewAny', TtxSession::class);

        $sessions = $service->index($request->user());

        return Inertia::render('Tenant/Ttx/Sessions/Index', [
            'sessions' => $sessions,
            'scenarios' => TtxExercise::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->whereHas('injects')
                ->orderBy('title')
                ->get(['id', 'title', 'playbook_id'])
                ->map(fn (TtxExercise $exercise) => ['id' => $exercise->id, 'title' => $exercise->title, 'recommended_playbook_id' => $exercise->playbook_id]),
            'playbooks' => TtxPlaybook::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->where('is_active', true)
                ->orderBy('title')
                ->get(['id', 'title'])
                ->map(fn (TtxPlaybook $playbook) => ['id' => $playbook->id, 'title' => $playbook->title]),
        ]);
    }

    public function show(Request $request, TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('view', $session);

        return response()->json($service->readModel($request->user(), $session));
    }

    public function prepare(Request $request, TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('prepare', $session);

        return Inertia::render(
            'Tenant/Ttx/Sessions/Prepare',
            $service->preparationReadModel($request->user(), $session)
        );
    }

    public function assign(Request $request, TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('assign', $session);
        $data = $request->validate(['user_id' => ['required', 'integer'], 'team_id' => ['required', 'integer']]);
        $participant = User::whereKey($data['user_id'])->firstOrFail();
        $team = TtxSessionTeam::whereKey($data['team_id'])->firstOrFail();
        $service->assignParticipant($request->user(), $session, $participant, $team);

        if ($request->expectsJson()) {
            return response()->json($service->preparationReadModel($request->user(), $session->fresh()));
        }

        return back();
    }

    public function storeTeam(Request $request, TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('assign', $session);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'not_regex:/^\s*$/'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $service->createTeam($request->user(), $session, $data['name'], $data['description'] ?? null);

        return response()->json($service->preparationReadModel($request->user(), $session->fresh()), 201);
    }

    public function removeTeam(Request $request, TtxSession $session, int $team, TtxSessionService $service)
    {
        Gate::authorize('assign', $session);
        $service->removeTeam($request->user(), $session, $team);

        return response()->json($service->preparationReadModel($request->user(), $session->fresh()));
    }

    public function updateTeamResponsibilities(Request $request, TtxSession $session, int $team, TtxSessionService $service)
    {
        Gate::authorize('assign', $session);
        $data = $request->validate([
            'responsibilities' => ['nullable', 'string', 'max:5000'],
        ]);
        $service->updateTeamResponsibilities($request->user(), $session, $team, $data['responsibilities']);

        return response()->json($service->preparationReadModel($request->user(), $session->fresh()));
    }

    public function storeResponsibilityAssignment(Request $request, TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('assign', $session);
        $data = $request->validate([
            'team_id' => ['required', 'integer'],
            'playbook_phase_key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]+$/'],
            'role' => ['required', 'string', Rule::in(['primary', 'support'])],
        ]);
        $service->assignResponsibility(
            $request->user(),
            $session,
            (int) $data['team_id'],
            $data['playbook_phase_key'],
            $data['role']
        );

        return response()->json($service->preparationReadModel($request->user(), $session->fresh()), 201);
    }

    public function updateResponsibilityAssignment(Request $request, TtxSession $session, int $assignment, TtxSessionService $service)
    {
        Gate::authorize('assign', $session);
        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(['primary', 'support'])],
            'team_id' => ['sometimes', 'required', 'integer'],
        ]);

        $service->updateResponsibilityAssignment($request->user(), $session, $assignment, $data['role'], isset($data['team_id']) ? (int) $data['team_id'] : null);

        return response()->json($service->preparationReadModel($request->user(), $session->fresh()));
    }

    public function removeResponsibilityAssignment(Request $request, TtxSession $session, int $assignment, TtxSessionService $service)
    {
        Gate::authorize('assign', $session);
        $service->removeResponsibilityAssignment($request->user(), $session, $assignment);

        return response()->json($service->preparationReadModel($request->user(), $session->fresh()));
    }

    public function remove(Request $request, TtxSession $session, int $participant, TtxSessionService $service)
    {
        Gate::authorize('prepare', $session);
        $service->removeParticipant($request->user(), $session, $participant);

        return response()->json($service->preparationReadModel($request->user(), $session->fresh()));
    }

    public function ready(Request $request, TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('prepare', $session);
        $session = $service->markReady($request->user(), $session);

        return response()->json($service->preparationReadModel($request->user(), $session));
    }

    public function start(TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('start', $session);

        return response()->json($service->readModel(request()->user(), $service->start(request()->user(), $session)));
    }

    public function advance(Request $request, TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('advance', $session);
        $data = $request->validate(['confirm_incomplete' => ['sometimes', 'boolean']]);

        return response()->json($service->advanceInject(
            $session,
            $request->user(),
            (bool) ($data['confirm_incomplete'] ?? false)
        ));
    }

    public function console(Request $request, TtxSession $session)
    {
        Gate::authorize('facilitate', $session);

        return Inertia::render('Tenant/Ttx/Sessions/FacilitatorConsole', [
            'sessionId' => $session->id,
        ]);
    }

    public function workspace(Request $request, TtxSession $session)
    {
        Gate::authorize('participate', $session);

        return Inertia::render('Tenant/Ttx/Sessions/ParticipantWorkspace', [
            'sessionId' => $session->id,
        ]);
    }

    public function debrief(Request $request, TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('debrief', $session);
        $payload = $service->debriefReadModel($request->user(), $session);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Tenant/Ttx/Sessions/Debrief', [
            'sessionId' => $session->id,
        ]);
    }

    public function result(TtxSession $session)
    {
        Gate::authorize('debrief', $session);
        abort_unless($session->status->value === 'completed', 409);

        return Inertia::render('Tenant/Ttx/Sessions/Debrief', [
            'sessionId' => $session->id,
            'resultMode' => true,
        ]);
    }

    public function participantResult(TtxSession $session)
    {
        Gate::authorize('participate', $session);
        abort_unless($session->status->value === 'completed', 409);

        return Inertia::render('Tenant/Ttx/Sessions/ParticipantWorkspace', [
            'sessionId' => $session->id,
            'resultMode' => true,
        ]);
    }

    public function updateEvaluation(Request $request, TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('debrief', $session);
        $data = $request->validate([
            'evaluations' => ['required', 'array', 'min:1', 'max:6'],
            'evaluations.*.dimension' => ['required', 'string', 'distinct', Rule::in(array_keys(TtxSessionService::EVALUATION_DIMENSIONS))],
            'evaluations.*.rating' => ['required', 'string', Rule::in(array_keys(TtxSessionService::EVALUATION_RATINGS))],
            'evaluations.*.evidence' => ['nullable', 'string', 'max:5000'],
            'evaluations.*.finding' => ['nullable', 'string', 'max:5000'],
        ]);
        $service->updateEvaluation($request->user(), $session, $data['evaluations']);

        return response()->json($service->debriefReadModel($request->user(), $session->fresh()));
    }

    public function storeActionItem(Request $request, TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('debrief', $session);
        $item = $service->createActionItem($request->user(), $session, $this->validateActionItem($request));

        return response()->json($item, 201);
    }

    public function updateActionItem(Request $request, TtxSession $session, TtxActionItem $actionItem, TtxSessionService $service)
    {
        Gate::authorize('debrief', $session);
        abort_unless($actionItem->session_id === $session->id, 404);
        $item = $service->updateActionItem($request->user(), $session, $actionItem, $this->validateActionItem($request));

        return response()->json($item);
    }

    public function updateAfterActionSummary(Request $request, TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('debrief', $session);
        $data = $request->validate([
            'overall_summary' => ['required', 'string', 'max:10000'],
            'strengths' => ['required', 'string', 'max:10000'],
            'improvement_areas' => ['required', 'string', 'max:10000'],
            'key_lessons' => ['required', 'string', 'max:10000'],
        ]);
        $service->updateAfterActionSummary($request->user(), $session, $data);

        return response()->json($service->debriefReadModel($request->user(), $session->fresh()));
    }

    public function complete(Request $request, TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('debrief', $session);
        $completed = $service->complete($request->user(), $session);

        return response()->json($service->debriefReadModel($request->user(), $completed));
    }

    public function storeResponse(Request $request, TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('respond', $session);
        $data = $request->validate($this->responseRules($session, true));
        $response = $service->storeResponse($request->user(), $session, (int) $data['session_inject_id'], $data);

        return response()->json($service->participantResponseReadModel($request->user(), $session, $response), 201);
    }

    public function updateResponse(Request $request, TtxSession $session, TtxSessionResponse $response, TtxSessionService $service)
    {
        abort_unless($response->session_id === $session->id, 404);
        Gate::authorize('respond', $session);
        $data = $request->validate($this->responseRules($session, false));
        $updated = $service->updateResponse($request->user(), $session, $response->id, (int) $data['expected_revision'], $data);

        return response()->json($service->participantResponseReadModel($request->user(), $session, $updated));
    }

    private function validateActionItem(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/'],
            'owner' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/'],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high'])],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['required', 'string', Rule::in(['open', 'completed'])],
            'capability_code' => ['nullable', 'string', Rule::in(['EX-1', 'EX-2', 'EX-3', 'EX-4', 'EX-5', 'EX-6'])],
            'playbook_phase_key' => ['nullable', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]+$/'],
            'category' => ['nullable', 'string', Rule::in(['corrective_action', 'playbook_improvement'])],
        ]);
    }

    private function responseRules(TtxSession $session, bool $creating): array
    {
        $required = ['required', 'string', 'max:10000', 'not_regex:/^\s*$/'];
        $optional = ['nullable', 'string', 'max:10000'];

        return [
            ...($creating
                ? ['session_inject_id' => ['required', 'integer']]
                : ['expected_revision' => ['required', 'integer', 'min:1']]),
            'decision' => $required,
            'rationale' => $session->response_contract_version >= 2 ? $required : $optional,
            'owner' => $optional,
            'immediate_actions' => $session->response_contract_version >= 2 ? $required : $optional,
            'coordination_handoff' => $session->response_contract_version >= 2 ? $required : $optional,
            'escalation' => $optional,
            'unknowns' => $optional,
            'notes' => $optional,
        ];
    }
}
