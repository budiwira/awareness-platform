<?php

namespace App\Services;

use App\Enums\TtxSessionInjectStatus;
use App\Enums\TtxSessionRole;
use App\Enums\TtxSessionStatus;
use App\Models\TtxExercise;
use App\Models\TtxSession;
use App\Models\TtxSessionInject;
use App\Models\TtxSessionParticipant;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TtxSessionService
{
    public function create(User $actor, TtxExercise $exercise, string $title, ?\DateTimeInterface $scheduledAt = null): TtxSession
    {
        abort_unless($actor->tenant_id === $exercise->tenant_id, 403);

        return DB::transaction(function () use ($actor, $exercise, $title, $scheduledAt) {
            $session = TtxSession::create([
                'tenant_id' => $actor->tenant_id,
                'exercise_id' => $exercise->id,
                'title' => $title,
                'created_by' => $actor->id,
                'status' => TtxSessionStatus::Draft,
                'scheduled_at' => $scheduledAt,
                'exercise_snapshot' => $this->exerciseSnapshot($exercise),
            ]);
            foreach ($exercise->injects()->get() as $inject) {
                TtxSessionInject::create([
                    'tenant_id' => $actor->tenant_id,
                    'session_id' => $session->id,
                    'inject_id' => $inject->id,
                    'order' => $inject->order,
                    'status' => TtxSessionInjectStatus::Pending,
                    'inject_snapshot' => ['title' => $inject->title, 'description' => $inject->description],
                ]);
            }
            Audit::log('ttx.session_created', $session, ['exercise_id' => $exercise->id]);
            return $session;
        });
    }

    public function assignParticipant(User $actor, TtxSession $session, User $participant, TtxSessionRole $role): TtxSessionParticipant
    {
        abort_unless($actor->tenant_id === $session->tenant_id && $participant->tenant_id === $session->tenant_id, 403);
        return DB::transaction(function () use ($session, $participant, $role) {
            $assignment = TtxSessionParticipant::create([
                'tenant_id' => $session->tenant_id,
                'session_id' => $session->id,
                'user_id' => $participant->id,
                'session_role' => $role,
            ]);
            Audit::log('ttx.session_participant_assigned', $assignment, ['session_id' => $session->id, 'user_id' => $participant->id, 'role' => $role->value]);
            return $assignment;
        });
    }

    public function markReady(TtxSession $session): TtxSession
    {
        if ($session->status !== TtxSessionStatus::Draft) {
            throw ValidationException::withMessages(['session' => 'Sesi hanya dapat disiapkan dari status draft.']);
        }

        $session->loadMissing('participants', 'injects');
        if (! $session->participants->contains(fn ($p) => $p->session_role === TtxSessionRole::Facilitator) || $session->injects->isEmpty()) {
            throw ValidationException::withMessages(['session' => 'Sesi harus memiliki fasilitator dan minimal satu inject.']);
        }
        $session->update(['status' => TtxSessionStatus::Ready]);
        Audit::log('ttx.session_ready', $session);
        return $session->fresh();
    }

    public function start(TtxSession $session): TtxSession
    {
        if ($session->status !== TtxSessionStatus::Ready) {
            throw ValidationException::withMessages(['session' => 'Sesi belum siap dimulai.']);
        }
        $session->update(['status' => TtxSessionStatus::InProgress, 'started_at' => now()]);
        Audit::log('ttx.session_started', $session);
        return $session->fresh();
    }

    public function advanceInject(TtxSession $session, User $actor): TtxSessionInject
    {
        if ($session->status !== TtxSessionStatus::InProgress) {
            throw ValidationException::withMessages(['session' => 'Inject hanya dapat dijalankan saat sesi berlangsung.']);
        }

        return DB::transaction(function () use ($session, $actor) {
            $current = $session->injects()->where('status', TtxSessionInjectStatus::Active)->lockForUpdate()->first();
            if ($current) {
                $current->update(['status' => TtxSessionInjectStatus::Locked, 'locked_at' => now()]);
            }
            $next = $session->injects()->where('status', TtxSessionInjectStatus::Pending)->lockForUpdate()->first();
            if (! $next) {
                throw ValidationException::withMessages(['inject' => 'Semua inject sudah diproses.']);
            }
            $next->update(['status' => TtxSessionInjectStatus::Active, 'released_at' => now(), 'released_by' => $actor->id]);
            Audit::log('ttx.session_inject_released', $next, ['session_id' => $session->id]);
            return TtxSessionInject::query()->findOrFail($next->id);
        });
    }

    public function readModel(User $user, TtxSession $session): array
    {
        $facilitator = $session->participants()->where('user_id', $user->id)->where('session_role', TtxSessionRole::Facilitator)->exists();
        return [
            'id' => $session->id,
            'title' => $session->title,
            'status' => $session->status->value,
            'scheduled_at' => $session->scheduled_at?->toISOString(),
            'participants' => $session->participants()->with('user:id,name')->get()->map(fn ($p) => ['id' => $p->user_id, 'name' => $p->user->name, 'role' => $p->session_role->value])->values(),
            'injects' => $session->injects()->get()->map(fn ($i) => ['id' => $i->id, 'order' => $i->order, 'status' => $i->status->value, 'snapshot' => $facilitator ? $i->inject_snapshot : ($i->status === TtxSessionInjectStatus::Active ? $i->inject_snapshot : null)])->values(),
        ];
    }

    private function exerciseSnapshot(TtxExercise $exercise): array
    {
        return ['title' => $exercise->title, 'scenario' => $exercise->scenario, 'objectives' => $exercise->objectives, 'scope' => $exercise->scope];
    }
}
