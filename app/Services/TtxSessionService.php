<?php

namespace App\Services;

use App\Enums\TtxSessionInjectStatus;
use App\Enums\TtxSessionRole;
use App\Enums\TtxSessionStatus;
use App\Models\TtxExercise;
use App\Models\TtxSession;
use App\Models\TtxSessionInject;
use App\Models\TtxSessionParticipant;
use App\Models\TtxSessionResponse;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TtxSessionService
{
    public function create(User $actor, TtxExercise $exercise, string $title, ?\DateTimeInterface $scheduledAt = null): TtxSession
    {
        abort_unless($actor->is_active && $actor->isTenantAdmin() && $actor->tenant_id === $exercise->tenant_id, 403);

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
        abort_unless($actor->is_active && $actor->isTenantAdmin() && $actor->tenant_id === $session->tenant_id, 403);
        abort_unless($participant->is_active && $participant->tenant_id === $session->tenant_id, 403);

        return DB::transaction(function () use ($session, $participant, $role) {
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless(in_array($lockedSession->status, [TtxSessionStatus::Draft, TtxSessionStatus::Ready], true), 409);
            $assignment = TtxSessionParticipant::create([
                'tenant_id' => $lockedSession->tenant_id,
                'session_id' => $lockedSession->id,
                'user_id' => $participant->id,
                'session_role' => $role,
            ]);
            Audit::log('ttx.session_participant_assigned', $assignment, ['session_id' => $session->id, 'user_id' => $participant->id, 'role' => $role->value]);

            return $assignment;
        });
    }

    public function markReady(User $actor, TtxSession $session): TtxSession
    {
        $this->assertPreparationActor($actor, $session);

        return DB::transaction(function () use ($actor, $session) {
            $session = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->assertPreparationActor($actor, $session);
            if ($session->status !== TtxSessionStatus::Draft) {
                throw ValidationException::withMessages(['session' => 'Sesi hanya dapat disiapkan dari status draft.']);
            }

            $session->loadMissing('participants', 'injects');
            $facilitators = $session->participants->where('session_role', TtxSessionRole::Facilitator);
            $nonFacilitators = $session->participants->where('session_role', '!=', TtxSessionRole::Facilitator);
            $allPending = $session->injects->every(fn ($inject) => $inject->status === TtxSessionInjectStatus::Pending);
            if (empty($session->exercise_snapshot)
                || $facilitators->count() !== 1
                || $nonFacilitators->isEmpty()
                || $session->injects->isEmpty()
                || ! $allPending
                || $session->started_at !== null
            ) {
                throw ValidationException::withMessages(['session' => 'Sesi belum memenuhi kontrak readiness.']);
            }
            $session->update(['status' => TtxSessionStatus::Ready]);
            Audit::log('ttx.session_ready', $session);

            return $session->fresh();
        });
    }

    public function start(User $actor, TtxSession $session): TtxSession
    {
        $this->assertRuntimeActor($actor, $session);

        return DB::transaction(function () use ($actor, $session) {
            $session = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->assertRuntimeActor($actor, $session);
            if ($session->status !== TtxSessionStatus::Ready) {
                throw ValidationException::withMessages(['session' => 'Sesi belum siap dimulai.']);
            }
            $injects = $session->injects()->lockForUpdate()->get();
            if ($injects->isEmpty() || ! $injects->every(fn ($inject) => $inject->status === TtxSessionInjectStatus::Pending)) {
                throw ValidationException::withMessages(['inject' => 'Semua inject harus berstatus pending sebelum dimulai.']);
            }
            $first = $injects->sortBy('order')->first();
            $now = now();
            $session->update(['status' => TtxSessionStatus::InProgress, 'started_at' => $now]);
            $first->update(['status' => TtxSessionInjectStatus::Active, 'released_at' => $now, 'released_by' => $actor->id]);
            Audit::log('ttx.session_started', $session);
            Audit::log('ttx.inject_released', $first, ['session_id' => $session->id, 'initial' => true]);

            return $session->fresh();
        });
    }

    public function advanceInject(TtxSession $session, User $actor): TtxSessionInject
    {
        $this->assertRuntimeActor($actor, $session);
        if ($session->status !== TtxSessionStatus::InProgress) {
            throw ValidationException::withMessages(['session' => 'Inject hanya dapat dijalankan saat sesi berlangsung.']);
        }

        return DB::transaction(function () use ($session, $actor) {
            // Lock order: Session → Current Inject → Current Response → Next Inject
            $session = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->assertRuntimeActor($actor, $session);
            if ($session->status !== TtxSessionStatus::InProgress) {
                throw ValidationException::withMessages(['session' => 'Inject hanya dapat dijalankan saat sesi berlangsung.']);
            }

            // Lock current ACTIVE inject
            $current = $session->injects()->where('status', TtxSessionInjectStatus::Active)->lockForUpdate()->first();
            if (! $current) {
                throw ValidationException::withMessages(['inject' => 'Tidak ada inject aktif untuk diselesaikan.']);
            }

            // Locate and lock official response (must exist with non-empty decision)
            $response = TtxSessionResponse::where('session_inject_id', $current->id)->lockForUpdate()->first();
            if (! $response) {
                throw ValidationException::withMessages(['response' => 'Response harus dibuat sebelum inject diselesaikan.']);
            }
            if (trim($response->decision) === '') {
                throw ValidationException::withMessages(['response' => 'Decision tidak boleh kosong.']);
            }
            if ($response->locked_at !== null) {
                throw ValidationException::withMessages(['response' => 'Response sudah terkunci.']);
            }

            // Lock next PENDING inject
            $next = $session->injects()->where('status', TtxSessionInjectStatus::Pending)->lockForUpdate()->first();

            // Update current inject → LOCKED
            $current->update(['status' => TtxSessionInjectStatus::Locked, 'locked_at' => now()]);
            Audit::log('ttx.inject_locked', $current, ['session_id' => $session->id]);

            // Lock response (does not increment revision — locked_at not in $fillable)
            DB::table('ttx_session_responses')
                ->where('id', $response->id)
                ->update(['locked_at' => now()]);
            Audit::log('ttx.response_locked', $response, [
                'session_id' => $session->id,
                'session_inject_id' => $current->id,
                'response_id' => $response->id,
                'revision' => $response->revision,
            ]);

            // Activate next or enter debrief
            if (! $next) {
                $session->update(['status' => TtxSessionStatus::Debrief, 'debrief_started_at' => now()]);
                Audit::log('ttx.debrief_started', $session);

                return $current;
            }

            $next->update(['status' => TtxSessionInjectStatus::Active, 'released_at' => now(), 'released_by' => $actor->id]);
            Audit::log('ttx.inject_released', $next, ['session_id' => $session->id]);

            return TtxSessionInject::query()->findOrFail($next->id);
        });
    }

    public function readModel(User $user, TtxSession $session): array
    {
        $actor = $session->participants()->where('user_id', $user->id)->first();
        $facilitator = $actor?->session_role === TtxSessionRole::Facilitator;
        $injects = $session->injects()->get();
        if (! $facilitator) {
            $injects = $injects->whereIn('status', [TtxSessionInjectStatus::Active, TtxSessionInjectStatus::Locked]);
        }
        $current = $injects->firstWhere('status', TtxSessionInjectStatus::Active);

        return [
            'id' => $session->id,
            'title' => $session->title,
            'status' => $session->status->value,
            'scheduled_at' => $session->scheduled_at?->toISOString(),
            'actor_role' => $actor?->session_role?->value,
            'progress' => ['current' => $current?->order, 'total' => $session->injects()->count()],
            'participants' => $session->participants()->with('user:id,name')->get()->map(fn ($p) => ['id' => $p->user_id, 'name' => $p->user->name, 'role' => $p->session_role->value])->values(),
            'injects' => $injects->map(fn ($i) => ['id' => $i->id, 'order' => $i->order, 'status' => $i->status->value, 'snapshot' => $i->inject_snapshot])->values(),
        ];
    }

    public function storeResponse(User $actor, TtxSession $session, int $injectId, array $data): TtxSessionResponse
    {
        $this->assertResponseActor($actor, $session);

        return DB::transaction(function () use ($actor, $session, $injectId, $data) {
            // Lock order: Session → Inject (compatible with start/advance)
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($lockedSession->status !== TtxSessionStatus::InProgress) {
                throw ValidationException::withMessages([
                    'session' => 'Response hanya dapat dibuat saat sesi berlangsung.',
                ]);
            }

            $lockedInject = TtxSessionInject::query()
                ->where('session_id', $session->id)
                ->where('id', $injectId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInject->status !== TtxSessionInjectStatus::Active) {
                throw ValidationException::withMessages([
                    'inject' => 'Inject harus berstatus aktif untuk membuat response.',
                ]);
            }

            // Application-level uniqueness check (before DB insert)
            if (TtxSessionResponse::where('session_inject_id', $injectId)->exists()) {
                abort(409, 'Response untuk inject ini sudah ada.');
            }

            // Guard against concurrent duplicate creation race at DB level
            try {
                $response = TtxSessionResponse::forceCreate([
                    'tenant_id' => $lockedSession->tenant_id,
                    'session_id' => $lockedSession->id,
                    'session_inject_id' => $injectId,
                    'decision' => $data['decision'],
                    'rationale' => $data['rationale'] ?? null,
                    'owner' => $data['owner'] ?? null,
                    'immediate_actions' => $data['immediate_actions'] ?? null,
                    'escalation' => $data['escalation'] ?? null,
                    'unknowns' => $data['unknowns'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'revision' => 1,
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                ]);
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'ttx_responses_one_per_inject')) {
                    abort(409, 'Response untuk inject ini sudah ada.');
                }
                throw $e;
            }

            // Audit inside same transaction
            Audit::log('ttx.response_saved', $response, [
                'session_id' => $lockedSession->id,
                'session_inject_id' => $injectId,
                'revision' => 1,
            ]);

            return $response;
        });
    }

    public function updateResponse(User $actor, TtxSession $session, int $responseId, int $expectedRevision, array $data): TtxSessionResponse
    {
        $this->assertResponseActor($actor, $session);

        return DB::transaction(function () use ($actor, $session, $responseId, $expectedRevision, $data) {
            // Lock order: Session → Inject (compatible with start/advance)
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($lockedSession->status !== TtxSessionStatus::InProgress) {
                throw ValidationException::withMessages([
                    'session' => 'Response hanya dapat diubah saat sesi berlangsung.',
                ]);
            }

            // Load response scoped to session (defense-in-depth)
            $response = TtxSessionResponse::where('id', $responseId)
                ->where('session_id', $session->id)
                ->firstOrFail();

            // Lock inject
            $lockedInject = TtxSessionInject::query()
                ->where('session_id', $session->id)
                ->where('id', $response->session_inject_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInject->status !== TtxSessionInjectStatus::Active) {
                throw ValidationException::withMessages([
                    'inject' => 'Inject harus berstatus aktif untuk mengubah response.',
                ]);
            }

            // Defense-in-depth: response immutability after progression lock
            if ($response->locked_at !== null) {
                throw ValidationException::withMessages([
                    'response' => 'Response sudah terkunci dan tidak dapat diubah.',
                ]);
            }

            // Atomic update with revision check (defense-in-depth: scoped to session_id)
            $affected = DB::table('ttx_session_responses')
                ->where('id', $responseId)
                ->where('session_id', $session->id)
                ->where('revision', $expectedRevision)
                ->update([
                    'decision' => $data['decision'],
                    'rationale' => $data['rationale'] ?? null,
                    'owner' => $data['owner'] ?? null,
                    'immediate_actions' => $data['immediate_actions'] ?? null,
                    'escalation' => $data['escalation'] ?? null,
                    'unknowns' => $data['unknowns'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'last_edited_by' => $actor->id,
                    'revision' => $expectedRevision + 1,
                ]);

            if ($affected === 0) {
                abort(409, 'Versi response tidak sesuai. Silakan muat ulang dan coba lagi.');
            }

            // Audit inside same transaction
            $fresh = TtxSessionResponse::findOrFail($responseId);
            Audit::log('ttx.response_saved', $fresh, [
                'session_id' => $lockedSession->id,
                'session_inject_id' => $response->session_inject_id,
                'revision' => $fresh->revision,
            ]);

            return $fresh;
        });
    }

    private function assertPreparationActor(User $actor, TtxSession $session): void
    {
        abort_unless($actor->is_active && $actor->isTenantAdmin() && $actor->tenant_id === $session->tenant_id, 403);
    }

    private function assertRuntimeActor(User $actor, TtxSession $session): void
    {
        abort_unless($actor->is_active && $actor->tenant_id === $session->tenant_id, 403);
        abort_unless($session->participants()
            ->where('user_id', $actor->id)
            ->where('session_role', TtxSessionRole::Facilitator)
            ->exists(), 403);
    }

    private function exerciseSnapshot(TtxExercise $exercise): array
    {
        return ['title' => $exercise->title, 'scenario' => $exercise->scenario, 'objectives' => $exercise->objectives, 'scope' => $exercise->scope];
    }

    private function assertResponseActor(User $actor, TtxSession $session): void
    {
        abort_unless($actor->is_active && $actor->tenant_id === $session->tenant_id, 403);
        abort_unless(
            $session->participants()->where('user_id', $actor->id)->exists(),
            403
        );
    }
}
