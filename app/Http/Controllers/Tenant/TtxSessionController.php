<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\TtxSessionRole;
use App\Http\Controllers\Controller;
use App\Models\TtxExercise;
use App\Models\TtxSession;
use App\Models\User;
use App\Services\TtxSessionService;
use Illuminate\Http\Request;

class TtxSessionController extends Controller
{
    public function store(Request $request, TtxExercise $exercise, TtxSessionService $service)
    {
        $this->authorize('create', TtxSession::class);
        abort_unless($exercise->tenant_id === $request->user()->tenant_id, 403);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'scheduled_at' => ['nullable', 'date']]);
        $session = $service->create($request->user(), $exercise, $data['title'], isset($data['scheduled_at']) ? new \DateTimeImmutable($data['scheduled_at']) : null);
        return redirect()->route('tenant.ttx.sessions.show', $session);
    }

    public function show(Request $request, TtxSession $session, TtxSessionService $service)
    {
        $this->authorize('view', $session);
        return response()->json($service->readModel($request->user(), $session));
    }

    public function assign(Request $request, TtxSession $session, TtxSessionService $service)
    {
        $this->authorize('assign', $session);
        $data = $request->validate(['user_id' => ['required', 'integer'], 'role' => ['required', 'in:facilitator,security,it_operations,people_hr,communications,management']]);
        $participant = User::whereKey($data['user_id'])->firstOrFail();
        $service->assignParticipant($request->user(), $session, $participant, TtxSessionRole::from($data['role']));
        return back();
    }

    public function ready(TtxSession $session, TtxSessionService $service)
    {
        $this->authorize('manage', $session);
        return response()->json($service->markReady($session));
    }

    public function start(TtxSession $session, TtxSessionService $service)
    {
        $this->authorize('manage', $session);
        return response()->json($service->start($session));
    }

    public function advance(Request $request, TtxSession $session, TtxSessionService $service)
    {
        $this->authorize('manage', $session);
        return response()->json($service->advanceInject($session, $request->user()));
    }
}
