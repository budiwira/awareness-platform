<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\TtxSessionRole;
use App\Http\Controllers\Controller;
use App\Models\TtxExercise;
use App\Models\TtxSession;
use App\Models\TtxSessionResponse;
use App\Models\User;
use App\Services\TtxSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

    public function show(Request $request, TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('view', $session);

        return response()->json($service->readModel($request->user(), $session));
    }

    public function assign(Request $request, TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('assign', $session);
        $data = $request->validate(['user_id' => ['required', 'integer'], 'role' => ['required', 'in:facilitator,security,it_operations,people_hr,communications,management']]);
        $participant = User::whereKey($data['user_id'])->firstOrFail();
        $service->assignParticipant($request->user(), $session, $participant, TtxSessionRole::from($data['role']));

        return back();
    }

    public function ready(TtxSession $session, TtxSessionService $service)
    {
        Gate::authorize('prepare', $session);

        return response()->json($service->readModel(request()->user(), $service->markReady(request()->user(), $session)));
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
}
