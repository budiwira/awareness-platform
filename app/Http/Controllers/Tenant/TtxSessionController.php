<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\TtxSessionRole;
use App\Http\Controllers\Controller;
use App\Models\TtxActionItem;
use App\Models\TtxExercise;
use App\Models\TtxSession;
use App\Models\TtxSessionResponse;
use App\Models\User;
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
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'scheduled_at' => ['nullable', 'date']]);
        $session = $service->create($request->user(), $exercise, $data['title'], isset($data['scheduled_at']) ? new \DateTimeImmutable($data['scheduled_at']) : null);

        return redirect()->route('tenant.ttx.sessions.show', $session);
    }

    public function index(Request $request, TtxSessionService $service)
    {
        Gate::authorize('viewAny', TtxSession::class);

        $sessions = $service->index($request->user());

        return Inertia::render('Tenant/Ttx/Sessions/Index', [
            'sessions' => $sessions,
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
        $data = $request->validate(['user_id' => ['required', 'integer'], 'role' => ['required', 'in:facilitator,security,it_operations,people_hr,communications,management']]);
        $participant = User::whereKey($data['user_id'])->firstOrFail();
        $service->assignParticipant($request->user(), $session, $participant, TtxSessionRole::from($data['role']));

        if ($request->expectsJson()) {
            return response()->json($service->preparationReadModel($request->user(), $session->fresh()));
        }

        return back();
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

        return response()->json($service->advanceInject($session, $request->user()));
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

    public function updateEvaluation(Request $request, TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('debrief', $session);
        $data = $request->validate([
            'evaluations' => ['required', 'array', 'min:1', 'max:6'],
            'evaluations.*.dimension' => ['required', 'string', 'distinct', Rule::in(array_keys(TtxSessionService::EVALUATION_DIMENSIONS))],
            'evaluations.*.rating' => ['required', 'string', Rule::in(array_keys(TtxSessionService::EVALUATION_RATINGS))],
            'evaluations.*.evidence' => ['nullable', 'string', 'max:5000'],
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
        $data = $request->validate([
            'session_inject_id' => ['required', 'integer'],
            'decision' => ['required', 'string', 'min:1'],
            'rationale' => ['nullable', 'string'],
            'owner' => ['nullable', 'string'],
            'immediate_actions' => ['nullable', 'string'],
            'escalation' => ['nullable', 'string'],
            'unknowns' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
        $response = $service->storeResponse($request->user(), $session, (int) $data['session_inject_id'], $data);

        return response()->json($response, 201);
    }

    public function updateResponse(Request $request, TtxSession $session, TtxSessionResponse $response, TtxSessionService $service)
    {
        abort_unless($response->session_id === $session->id, 404);
        Gate::authorize('respond', $session);
        $data = $request->validate([
            'expected_revision' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'string', 'min:1'],
            'rationale' => ['nullable', 'string'],
            'owner' => ['nullable', 'string'],
            'immediate_actions' => ['nullable', 'string'],
            'escalation' => ['nullable', 'string'],
            'unknowns' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
        $updated = $service->updateResponse($request->user(), $session, $response->id, (int) $data['expected_revision'], $data);

        return response()->json($updated);
    }

    private function validateActionItem(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/'],
            'owner' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/'],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high'])],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['required', 'string', Rule::in(['open', 'completed'])],
        ]);
    }
}
