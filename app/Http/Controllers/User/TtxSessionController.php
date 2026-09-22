<?php

namespace App\Http\Controllers\User;

use App\Enums\TtxSessionRole;
use App\Http\Controllers\Controller;
use App\Models\TtxSessionParticipant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TtxSessionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->tenant_id !== null, 403);

        $sessions = TtxSessionParticipant::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->with('session:id,title,status')
            ->get()
            ->filter(fn (TtxSessionParticipant $assignment) => $assignment->session !== null)
            ->map(function (TtxSessionParticipant $assignment): array {
                $facilitator = $assignment->session_role === TtxSessionRole::Facilitator;
                $status = $assignment->session->status->value;

                return [
                    'id' => $assignment->session->id,
                    'title' => $assignment->session->title,
                    'status' => $status,
                    'role' => $assignment->session_role->value,
                    'action_label' => $facilitator
                        ? (in_array($status, ['debrief', 'completed'], true) ? 'Buka Debrief' : 'Buka Konsol Fasilitator')
                        : $this->participantActionLabel($status),
                    'action_url' => route(
                        $facilitator
                            ? (in_array($status, ['debrief', 'completed'], true) ? 'tenant.ttx.sessions.debrief' : 'tenant.ttx.sessions.console')
                            : 'tenant.ttx.sessions.workspace',
                        $assignment->session->id
                    ),
                ];
            })
            ->sortByDesc('id')
            ->values();

        return Inertia::render('User/Ttx/Index', [
            'sessions' => $sessions,
        ]);
    }

    private function participantActionLabel(string $status): string
    {
        return match ($status) {
            'ready' => 'Menunggu / Buka Workspace',
            'in_progress' => 'Buka Workspace',
            'debrief', 'completed' => 'Lihat Workspace',
            default => 'Menunggu',
        };
    }
}
