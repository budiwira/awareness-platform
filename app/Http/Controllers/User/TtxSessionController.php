<?php

namespace App\Http\Controllers\User;

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
            ->with(['team:id,name', 'session:id,title,status,exercise_snapshot,created_by', 'session.facilitator:id,name'])
            ->get()
            ->filter(fn (TtxSessionParticipant $assignment) => $assignment->session !== null)
            ->filter(fn (TtxSessionParticipant $assignment) => $assignment->session->status->value !== 'draft')
            ->map(function (TtxSessionParticipant $assignment): array {
                $status = $assignment->session->status->value;

                return [
                    'id' => $assignment->session->id,
                    'title' => $assignment->session->title,
                    'scenario' => is_array($assignment->session->exercise_snapshot)
                        ? ($assignment->session->exercise_snapshot['title'] ?? null)
                        : null,
                    'status' => $status,
                    'team_name' => $assignment->team?->name,
                    'facilitator_name' => $assignment->session->facilitator?->name,
                    'action_label' => $this->participantActionLabel($status),
                    'action_url' => $status === 'completed'
                        ? route('tenant.ttx.sessions.participant-result', $assignment->session->id)
                        : route('tenant.ttx.sessions.workspace', $assignment->session->id),
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
            'ready' => 'Buka Briefing',
            'in_progress' => 'Lanjutkan Exercise',
            'debrief' => 'Exercise Menunggu Review',
            'completed' => 'Lihat Hasil',
            default => 'Menunggu',
        };
    }
}
