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
use Illuminate\Support\Facades\Gate;
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
            abort_if($lockedSession->participants()->where('user_id', $participant->id)->exists(), 409, 'Pengguna sudah terdaftar pada sesi ini.');
            abort_if($role === TtxSessionRole::Facilitator && $lockedSession->participants()->where('session_role', $role)->exists(), 409, 'Sesi sudah memiliki fasilitator.');
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

    public function removeParticipant(User $actor, TtxSession $session, int $participantId): void
    {
        $this->assertPreparationActor($actor, $session);

        DB::transaction(function () use ($actor, $session, $participantId) {
            $session = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->assertPreparationActor($actor, $session);
            $participant = $session->participants()->where('tenant_id', $session->tenant_id)->findOrFail($participantId);
            $session->load('participants', 'injects');
            abort_unless($this->canRemoveParticipant($session, $participant), 409, 'Peserta tidak dapat dihapus karena status atau kesiapan sesi.');

            $participant->delete();
            Audit::log('ttx.session_participant_removed', $participant, [
                'session_id' => $session->id,
                'user_id' => $participant->user_id,
                'role' => $participant->session_role->value,
            ]);
        });
    }

    private function canRemoveParticipant(TtxSession $session, TtxSessionParticipant $participant): bool
    {
        if ($session->status === TtxSessionStatus::Draft) {
            return true;
        }

        return $session->status === TtxSessionStatus::Ready
            && $participant->session_role !== TtxSessionRole::Facilitator
            && $session->participants->where('session_role', TtxSessionRole::Facilitator)->count() === 1
            && $session->participants->where('session_role', '!=', TtxSessionRole::Facilitator)->count() > 1
            && ! empty($session->exercise_snapshot)
            && $session->injects->isNotEmpty()
            && $session->injects->every(fn ($inject) => $inject->status === TtxSessionInjectStatus::Pending)
            && $session->started_at === null;
    }

    /**
     * Safe list projection for the Tenant Admin sessions index.
     * Newest created first. Never exposes snapshots, injects, or responses.
     */
    public function index(User $actor): array
    {
        abort_unless($actor->is_active && $actor->isTenantAdmin() && $actor->tenant_id !== null, 403);

        return TtxSession::query()
            ->where('tenant_id', $actor->tenant_id)
            ->with(['participants.user:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (TtxSession $session) {
                $facilitator = $session->participants
                    ->firstWhere('session_role', TtxSessionRole::Facilitator);

                return [
                    'id' => $session->id,
                    'title' => $session->title,
                    'status' => $session->status->value,
                    'scheduled_at' => $session->scheduled_at?->toISOString(),
                    'facilitator_name' => $facilitator?->user?->name,
                    'participant_count' => $session->participants->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Dedicated, read-only preparation read model.
     *
     * Returns an explicit allowlisted payload only. It never exposes
     * exercise_snapshot, inject_snapshot, inject content, future injects,
     * responses, facilitator notes, or any runtime-sensitive data.
     */
    public function preparationReadModel(User $actor, TtxSession $session): array
    {
        abort_unless($actor->is_active && $actor->isTenantAdmin() && $actor->tenant_id === $session->tenant_id, 403);

        $session->loadMissing(['participants.user:id,name', 'injects']);

        $facilitators = $session->participants->where('session_role', TtxSessionRole::Facilitator);
        $facilitator = $facilitators->first();

        // Historical exercise title: extract ONLY the exact scalar field required.
        // Never serialize the snapshot object/array itself.
        $exerciseTitle = null;
        $snapshot = $session->exercise_snapshot;
        if (is_array($snapshot) && isset($snapshot['title']) && is_string($snapshot['title'])) {
            $exerciseTitle = $snapshot['title'];
        }

        $injects = $session->injects;
        $allPending = $injects->isNotEmpty()
            && $injects->every(fn ($inject) => $inject->status === TtxSessionInjectStatus::Pending);

        $readiness = [
            'has_exercise_snapshot' => ! empty($snapshot),
            'has_inject' => $injects->isNotEmpty(),
            'exactly_one_facilitator' => $facilitators->count() === 1,
            'has_participants' => $session->participants->where('session_role', '!=', TtxSessionRole::Facilitator)->isNotEmpty(),
            'all_injects_pending' => $allPending,
            'not_started' => $session->started_at === null,
        ];
        $readiness['ready'] = ! in_array(false, $readiness, true);

        return [
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'status' => $session->status->value,
                'scheduled_at' => $session->scheduled_at?->toISOString(),
            ],
            'exercise_title' => $exerciseTitle,
            'inject_count' => $injects->count(),
            'facilitator' => $facilitator ? [
                'assignment_id' => $facilitator->id,
                'can_remove' => $this->canRemoveParticipant($session, $facilitator),
                'name' => $facilitator->user?->name,
                'role' => $facilitator->session_role->value,
            ] : null,
            'facilitator_count' => $facilitators->count(),
            'participants' => $session->participants
                ->map(fn ($p) => [
                    'id' => $p->user_id,
                    'assignment_id' => $p->id,
                    'can_remove' => $this->canRemoveParticipant($session, $p),
                    'name' => $p->user?->name,
                    'role' => $p->session_role->value,
                ])
                ->values()
                ->all(),
            'readiness' => $readiness,
            'can_assign' => Gate::forUser($actor)->allows('assign', $session),
            'can_assign_facilitator' => Gate::forUser($actor)->allows('assign', $session) && $facilitator === null,
            'assignable_users' => User::query()
                ->where('tenant_id', $session->tenant_id)
                ->where('is_active', true)
                ->whereNotIn('id', $session->participants->pluck('user_id'))
                ->orderBy('name')->orderBy('id')->get(['id', 'name'])
                ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name])->all(),
            'role_options' => [
                ['value' => 'facilitator', 'label' => 'Fasilitator'],
                ['value' => 'security', 'label' => 'Keamanan'],
                ['value' => 'it_operations', 'label' => 'Operasional TI'],
                ['value' => 'people_hr', 'label' => 'SDM'],
                ['value' => 'communications', 'label' => 'Komunikasi'],
                ['value' => 'management', 'label' => 'Manajemen'],
            ],
            // Reflects the SAME semantics as TtxSessionPolicy::facilitate for the
            // current actor. Tenant Admin is NOT automatically allowed.
            'can_open_console' => Gate::forUser($actor)->allows('facilitate', $session),
        ];
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

        // Build response map: session_inject_id → response
        // Only fetch responses for injects where response exposure is allowed.
        // For participants: Active + Locked (already the only injects in $injects).
        // For facilitators: Active + Locked only. Pending/future injects must NOT expose response data.
        $responseableIds = $injects
            ->whereIn('status', [TtxSessionInjectStatus::Active, TtxSessionInjectStatus::Locked])
            ->pluck('id')
            ->all();
        $responses = TtxSessionResponse::whereIn('session_inject_id', $responseableIds)->get()->keyBy('session_inject_id');

        return [
            'id' => $session->id,
            'title' => $session->title,
            'status' => $session->status->value,
            'scheduled_at' => $session->scheduled_at?->toISOString(),
            'actor_role' => $actor?->session_role?->value,
            'progress' => ['current' => $current?->order, 'total' => $session->injects()->count()],
            'participants' => $session->participants()->with('user:id,name')->get()->map(fn ($p) => ['id' => $p->user_id, 'name' => $p->user->name, 'role' => $p->session_role->value])->values(),
            'injects' => $injects->map(function ($i) use ($responses) {
                $payload = [
                    'id' => $i->id,
                    'order' => $i->order,
                    'status' => $i->status->value,
                    'snapshot' => $i->inject_snapshot,
                ];

                // Pending injects must NOT contain a response key at all.
                // Only Active and Locked injects expose response data.
                if ($i->status !== TtxSessionInjectStatus::Pending) {
                    $payload['response'] = $this->safeResponsePayload($responses->get($i->id));
                }

                return $payload;
            })->values(),
        ];
    }

    /**
     * Build a response payload exposing only frontend-required fields.
     * Returns null when no response exists for this inject.
     */
    private function safeResponsePayload(?TtxSessionResponse $response): ?array
    {
        if (! $response) {
            return null;
        }

        return [
            'id' => $response->id,
            'decision' => $response->decision,
            'rationale' => $response->rationale,
            'owner' => $response->owner,
            'immediate_actions' => $response->immediate_actions,
            'escalation' => $response->escalation,
            'unknowns' => $response->unknowns,
            'notes' => $response->notes,
            'revision' => $response->revision,
            'submitted_at' => $response->submitted_at->toISOString(),
            'locked_at' => $response->locked_at?->toISOString(),
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
