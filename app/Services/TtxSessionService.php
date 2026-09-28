<?php

namespace App\Services;

use App\Domain\Tabletop\ExerciseCapabilityCatalog;
use App\Domain\Tabletop\PlaybookPhaseStructure;
use App\Enums\TtxSessionInjectStatus;
use App\Enums\TtxSessionStatus;
use App\Models\TtxActionItem;
use App\Models\TtxAfterActionSummary;
use App\Models\TtxExercise;
use App\Models\TtxPlaybook;
use App\Models\TtxSession;
use App\Models\TtxSessionEvaluation;
use App\Models\TtxSessionInject;
use App\Models\TtxSessionParticipant;
use App\Models\TtxSessionResponse;
use App\Models\TtxSessionResponsibilityAssignment;
use App\Models\TtxSessionTeam;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TtxSessionService
{
    public const EVALUATION_DIMENSIONS = [
        'detection_triage' => ['code' => 'EX-1', 'label' => 'Detection & Triage'],
        'escalation_ownership' => ['code' => 'EX-2', 'label' => 'Escalation & Ownership'],
        'containment_decision' => ['code' => 'EX-3', 'label' => 'Containment Decision'],
        'cross_functional_coordination' => ['code' => 'EX-4', 'label' => 'Cross-functional Coordination'],
        'incident_communication' => ['code' => 'EX-5', 'label' => 'Incident Communication'],
        'recovery_improvement' => ['code' => 'EX-6', 'label' => 'Recovery & Improvement'],
    ];

    public const EVALUATION_RATINGS = [
        'needs_improvement' => 'Perlu Perbaikan',
        'developing' => 'Berkembang',
        'effective' => 'Efektif',
        'strong' => 'Kuat',
    ];

    public function create(User $actor, TtxExercise $exercise, string $title, ?\DateTimeInterface $scheduledAt = null, ?TtxPlaybook $playbook = null): TtxSession
    {
        abort_unless($actor->is_active && $actor->isTenantAdmin() && $actor->tenant_id === $exercise->tenant_id, 403);
        $playbook ??= $exercise->playbook;
        abort_unless($playbook !== null && $playbook->is_active && $playbook->tenant_id === $actor->tenant_id, 403);

        return DB::transaction(function () use ($actor, $exercise, $title, $scheduledAt, $playbook) {
            $session = TtxSession::create([
                'tenant_id' => $actor->tenant_id,
                'exercise_id' => $exercise->id,
                'playbook_id' => $playbook->id,
                'title' => $title,
                'created_by' => $actor->id,
                'status' => TtxSessionStatus::Draft,
                'response_contract_version' => 2,
                'scheduled_at' => $scheduledAt,
                'exercise_snapshot' => $this->exerciseSnapshot($exercise),
                'playbook_snapshot' => $this->playbookSnapshot($playbook),
            ]);
            foreach ($exercise->injects()->get() as $inject) {
                $participantContent = $this->participantInjectContent($inject->description);
                TtxSessionInject::create([
                    'tenant_id' => $actor->tenant_id,
                    'session_id' => $session->id,
                    'inject_id' => $inject->id,
                    'order' => $inject->order,
                    'status' => TtxSessionInjectStatus::Pending,
                    'inject_snapshot' => [
                        'title' => $inject->title,
                        'description' => $inject->description,
                        'capability_codes' => ExerciseCapabilityCatalog::normalize($inject->capability_codes, 'inject.capability_codes'),
                        ...$participantContent,
                    ],
                ]);
            }
            Audit::log('ttx.session_created', $session, ['exercise_id' => $exercise->id]);

            return $session;
        });
    }

    public function createTeam(User $actor, TtxSession $session, string $name, ?string $description = null): TtxSessionTeam
    {
        $this->assertPreparationActor($actor, $session);

        return DB::transaction(function () use ($session, $name, $description) {
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($lockedSession->status === TtxSessionStatus::Draft, 409, 'Tim hanya dapat diubah saat sesi masih draft.');
            $team = TtxSessionTeam::forceCreate([
                'tenant_id' => $lockedSession->tenant_id,
                'session_id' => $lockedSession->id,
                'name' => trim($name),
                'description' => filled($description) ? trim($description) : null,
            ]);
            Audit::log('ttx.session_team_created', $team, ['session_id' => $lockedSession->id]);

            return $team;
        });
    }

    public function updateTeamResponsibilities(User $actor, TtxSession $session, int $teamId, ?string $responsibilities): TtxSessionTeam
    {
        $this->assertPreparationActor($actor, $session);

        return DB::transaction(function () use ($session, $teamId, $responsibilities) {
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($lockedSession->status === TtxSessionStatus::Draft, 409, 'Tanggung jawab tim hanya dapat diubah saat sesi masih draft.');
            $team = $lockedSession->teams()
                ->where('tenant_id', $lockedSession->tenant_id)
                ->lockForUpdate()
                ->findOrFail($teamId);
            $team->update(['responsibilities' => filled($responsibilities) ? trim($responsibilities) : null]);
            Audit::log('ttx.session_team_responsibilities_updated', $team, ['session_id' => $lockedSession->id]);

            return $team->fresh();
        });
    }

    public function assignResponsibility(User $actor, TtxSession $session, int $teamId, string $phaseKey, string $role): TtxSessionResponsibilityAssignment
    {
        $this->assertPreparationActor($actor, $session);

        return DB::transaction(function () use ($session, $teamId, $phaseKey, $role) {
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($lockedSession->status === TtxSessionStatus::Draft, 409, 'Ownership tanggung jawab hanya dapat diubah saat sesi masih draft.');
            abort_unless(in_array($role, ['primary', 'support'], true), 422, 'Role tanggung jawab tidak valid.');
            abort_unless(in_array($phaseKey, PlaybookPhaseStructure::keysFromSnapshot($lockedSession->playbook_snapshot), true), 422, 'Playbook phase tidak tersedia pada snapshot sesi.');

            $team = $lockedSession->teams()
                ->where('tenant_id', $lockedSession->tenant_id)
                ->lockForUpdate()
                ->findOrFail($teamId);

            try {
                $assignment = TtxSessionResponsibilityAssignment::forceCreate([
                    'tenant_id' => $lockedSession->tenant_id,
                    'session_id' => $lockedSession->id,
                    'session_team_id' => $team->id,
                    'playbook_phase_key' => $phaseKey,
                    'role' => $role,
                ]);
            } catch (QueryException $exception) {
                if (str_contains($exception->getMessage(), 'ttx_responsibility_team_phase_unique')) {
                    throw ValidationException::withMessages(['responsibility' => 'Tim sudah memiliki assignment untuk Playbook phase ini.']);
                }
                if (str_contains($exception->getMessage(), 'ttx_responsibility_one_primary_per_phase')) {
                    throw ValidationException::withMessages(['responsibility' => 'Playbook phase ini sudah memiliki PRIMARY owner.']);
                }
                throw $exception;
            }

            Audit::log('ttx.responsibility_assigned', $assignment, [
                'session_id' => $lockedSession->id,
                'session_team_id' => $team->id,
                'playbook_phase_key' => $phaseKey,
                'role' => $role,
            ]);

            return $assignment;
        });
    }

    public function updateResponsibilityAssignment(User $actor, TtxSession $session, int $assignmentId, string $role, ?int $teamId = null): TtxSessionResponsibilityAssignment
    {
        $this->assertPreparationActor($actor, $session);

        return DB::transaction(function () use ($session, $assignmentId, $role, $teamId) {
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($lockedSession->status === TtxSessionStatus::Draft, 409, 'Ownership tanggung jawab hanya dapat diubah saat sesi masih draft.');
            abort_unless(in_array($role, ['primary', 'support'], true), 422, 'Role tanggung jawab tidak valid.');
            $assignment = $lockedSession->responsibilityAssignments()->lockForUpdate()->findOrFail($assignmentId);

            // A Primary selection moves its existing assignment atomically. The
            // session lock serializes this with READY and other ownership edits.
            if ($teamId !== null && $teamId !== $assignment->session_team_id) {
                abort_unless($assignment->role === 'primary' && $role === 'primary', 422, 'Hanya Primary owner yang dapat dipindahkan ke tim lain.');
                $team = $lockedSession->teams()
                    ->where('tenant_id', $lockedSession->tenant_id)
                    ->lockForUpdate()->findOrFail($teamId);
                $support = $lockedSession->responsibilityAssignments()
                    ->where('session_team_id', $team->id)
                    ->where('playbook_phase_key', $assignment->playbook_phase_key)
                    ->lockForUpdate()->first();
                if ($support !== null) {
                    abort_unless($support->role === 'support', 409, 'Primary owner telah berubah. Muat ulang persiapan.');
                    $context = [
                        'session_id' => $lockedSession->id,
                        'session_team_id' => $team->id,
                        'playbook_phase_key' => $support->playbook_phase_key,
                        'role' => 'support',
                    ];
                    $support->delete();
                    Audit::log('ttx.responsibility_removed', $support, $context);
                }
                $assignment->forceFill(['session_team_id' => $team->id]);
            }

            try {
                $assignment->forceFill(['role' => $role])->save();
            } catch (QueryException $exception) {
                if (str_contains($exception->getMessage(), 'ttx_responsibility_one_primary_per_phase')) {
                    throw ValidationException::withMessages(['responsibility' => 'Playbook phase ini sudah memiliki PRIMARY owner.']);
                }
                throw $exception;
            }

            Audit::log('ttx.responsibility_changed', $assignment, [
                'session_id' => $lockedSession->id,
                'session_team_id' => $assignment->session_team_id,
                'role' => $role,
            ]);

            return $assignment->fresh();
        });
    }

    public function removeResponsibilityAssignment(User $actor, TtxSession $session, int $assignmentId): void
    {
        $this->assertPreparationActor($actor, $session);

        DB::transaction(function () use ($session, $assignmentId) {
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($lockedSession->status === TtxSessionStatus::Draft, 409, 'Ownership tanggung jawab hanya dapat diubah saat sesi masih draft.');
            $assignment = $lockedSession->responsibilityAssignments()->lockForUpdate()->findOrFail($assignmentId);
            $context = [
                'session_id' => $lockedSession->id,
                'session_team_id' => $assignment->session_team_id,
                'playbook_phase_key' => $assignment->playbook_phase_key,
                'role' => $assignment->role,
            ];
            $assignment->delete();
            Audit::log('ttx.responsibility_removed', $assignment, $context);
        });
    }

    public function removeTeam(User $actor, TtxSession $session, int $teamId): void
    {
        $this->assertPreparationActor($actor, $session);

        DB::transaction(function () use ($session, $teamId) {
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($lockedSession->status === TtxSessionStatus::Draft, 409, 'Tim hanya dapat diubah saat sesi masih draft.');
            $team = $lockedSession->teams()->lockForUpdate()->findOrFail($teamId);
            abort_if($team->participants()->exists(), 409, 'Tim yang masih memiliki peserta tidak dapat dihapus.');
            abort_if($team->responsibilityAssignments()->exists(), 409, 'Hapus ownership tanggung jawab tim sebelum menghapus tim.');
            $team->delete();
            Audit::log('ttx.session_team_removed', $team, ['session_id' => $lockedSession->id]);
        });
    }

    public function assignParticipant(User $actor, TtxSession $session, User $participant, TtxSessionTeam $team): TtxSessionParticipant
    {
        $this->assertPreparationActor($actor, $session);
        abort_unless($participant->is_active && $participant->isUser() && $participant->tenant_id === $session->tenant_id, 403);

        return DB::transaction(function () use ($session, $participant, $team) {
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($lockedSession->status === TtxSessionStatus::Draft, 409, 'Peserta hanya dapat diubah saat sesi masih draft.');
            $lockedTeam = TtxSessionTeam::query()
                ->where('tenant_id', $lockedSession->tenant_id)
                ->where('session_id', $lockedSession->id)
                ->lockForUpdate()
                ->findOrFail($team->id);
            abort_if($lockedSession->participants()->where('user_id', $participant->id)->exists(), 409, 'Pengguna sudah terdaftar pada sesi ini.');
            $assignment = TtxSessionParticipant::create([
                'tenant_id' => $lockedSession->tenant_id,
                'session_id' => $lockedSession->id,
                'user_id' => $participant->id,
                'team_id' => $lockedTeam->id,
                'session_role' => null,
            ]);
            Audit::log('ttx.session_participant_assigned', $assignment, ['session_id' => $session->id, 'user_id' => $participant->id, 'team_id' => $lockedTeam->id]);

            return $assignment;
        });
    }

    public function removeParticipant(User $actor, TtxSession $session, int $participantId): void
    {
        $this->assertPreparationActor($actor, $session);

        DB::transaction(function () use ($actor, $session, $participantId) {
            $session = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->assertPreparationActor($actor, $session);
            abort_unless($session->status === TtxSessionStatus::Draft, 409, 'Peserta hanya dapat diubah saat sesi masih draft.');
            $participant = $session->participants()->where('tenant_id', $session->tenant_id)->findOrFail($participantId);
            $session->load('participants', 'injects');
            abort_unless($this->canRemoveParticipant($session, $participant), 409, 'Peserta tidak dapat dihapus karena status atau kesiapan sesi.');

            $participant->delete();
            Audit::log('ttx.session_participant_removed', $participant, [
                'session_id' => $session->id,
                'user_id' => $participant->user_id,
                'team_id' => $participant->team_id,
            ]);
        });
    }

    private function canRemoveParticipant(TtxSession $session, TtxSessionParticipant $participant): bool
    {
        return $session->status === TtxSessionStatus::Draft;
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
            ->where('created_by', $actor->id)
            ->with(['facilitator:id,name', 'participants', 'teams'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (TtxSession $session) {
                $status = $session->status->value;

                return [
                    'id' => $session->id,
                    'title' => $session->title,
                    'scenario' => is_array($session->exercise_snapshot)
                        ? ($session->exercise_snapshot['title'] ?? null)
                        : null,
                    'status' => $session->status->value,
                    'scheduled_at' => $session->scheduled_at?->toISOString(),
                    'facilitator_name' => $session->facilitator?->name,
                    'participant_count' => $session->participants->count(),
                    'team_count' => $session->teams->count(),
                    'action_label' => match ($status) {
                        'draft' => 'Siapkan Session',
                        'ready' => 'Buka Facilitator Console',
                        'in_progress' => 'Lanjutkan Exercise',
                        'debrief' => 'Buka Debrief',
                        'completed' => 'Lihat Hasil',
                    },
                    'action_url' => match ($status) {
                        'draft' => route('tenant.ttx.sessions.prepare', $session),
                        'debrief' => route('tenant.ttx.sessions.debrief', $session),
                        'completed' => route('tenant.ttx.sessions.result', $session),
                        default => route('tenant.ttx.sessions.console', $session),
                    },
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
        $this->assertPreparationActor($actor, $session);

        $session->loadMissing(['facilitator:id,name,role,is_active,tenant_id', 'teams.participants.user:id,name,role,is_active,tenant_id', 'teams.responsibilityAssignments', 'participants.user:id,name,role,is_active,tenant_id', 'injects', 'responsibilityAssignments']);

        // Preparation receives an allowlisted historical context, never the
        // complete snapshot or inject content.
        $exerciseTitle = null;
        $snapshot = $session->exercise_snapshot;
        if (is_array($snapshot) && isset($snapshot['title']) && is_string($snapshot['title'])) {
            $exerciseTitle = $snapshot['title'];
        }

        $injects = $session->injects;
        $readiness = $this->readiness($session);
        $canManageRoster = Gate::forUser($actor)->allows('assign', $session);
        $canMarkReady = $readiness['can_mark_ready'];
        $canOpenConsole = Gate::forUser($actor)->allows('facilitate', $session);

        return [
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'status' => $session->status->value,
                'scheduled_at' => $session->scheduled_at?->toISOString(),
            ],
            'exercise_title' => $exerciseTitle,
            'exercise_context' => is_array($snapshot) ? collect($snapshot)->only([
                'title', 'scenario', 'objectives', 'scope', 'capability_codes',
            ])->all() : null,
            'playbook' => $this->safePlaybookReference($session->playbook_snapshot),
            'capabilities' => $this->capabilitiesForSnapshot($snapshot),
            'relevant_playbook_phase_keys' => PlaybookPhaseStructure::relevantKeys($session->playbook_snapshot, $session->exercise_snapshot),
            'inject_count' => $injects->count(),
            'facilitator' => $session->facilitator ? [
                'id' => $session->facilitator->id,
                'name' => $session->facilitator->name,
            ] : null,
            'participants' => $session->participants
                ->map(fn ($p) => [
                    'id' => $p->user_id,
                    'assignment_id' => $p->id,
                    'can_remove' => $this->canRemoveParticipant($session, $p),
                    'name' => $p->user?->name,
                    'team_id' => $p->team_id,
                ])
                ->values()
                ->all(),
            'teams' => $session->teams->map(fn (TtxSessionTeam $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'responsibilities' => $team->responsibilities,
                'responsibility_assignments' => $team->responsibilityAssignments->map(fn (TtxSessionResponsibilityAssignment $assignment) => [
                    'id' => $assignment->id,
                    'playbook_phase_key' => $assignment->playbook_phase_key,
                    'role' => $assignment->role,
                ])->values()->all(),
                'participant_count' => $team->participants->count(),
                'can_remove' => $session->status === TtxSessionStatus::Draft && $team->participants->isEmpty(),
            ])->values()->all(),
            'readiness' => $readiness,
            'permissions' => [
                'can_manage_roster' => $canManageRoster,
                'can_mark_ready' => $canMarkReady,
                'can_open_console' => $canOpenConsole,
            ],
            'can_assign' => $canManageRoster,
            'assignable_users' => User::query()
                ->where('tenant_id', $session->tenant_id)
                ->where('is_active', true)
                ->where('role', 'user')
                ->whereNotIn('id', $session->participants->pluck('user_id'))
                ->orderBy('name')->orderBy('id')->get(['id', 'name'])
                ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name])->all(),
            // Mirrors TtxSessionPolicy::facilitate: only the creator Tenant Admin.
            'can_open_console' => $canOpenConsole,
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
            if (! $this->readiness($session)['ready']) {
                throw ValidationException::withMessages(['session' => 'Sesi belum memenuhi kontrak readiness.']);
            }
            $session->update(['status' => TtxSessionStatus::Ready]);
            Audit::log('ttx.session_ready', $session);

            return $session->fresh();
        });
    }

    private function readiness(TtxSession $session): array
    {
        $session->loadMissing(['facilitator', 'teams.participants', 'participants.user', 'responsibilityAssignments']);
        $hasFacilitator = $session->facilitator !== null
            && $session->facilitator->is_active
            && $session->facilitator->isTenantAdmin()
            && $session->facilitator->tenant_id === $session->tenant_id;
        $hasTeams = $session->teams->isNotEmpty();
        $hasParticipant = $session->participants->isNotEmpty();
        $participantsValid = $hasParticipant && $session->participants->every(fn (TtxSessionParticipant $participant) => $participant->team_id !== null
            && $session->teams->contains('id', $participant->team_id)
            && $participant->user->is_active
            && $participant->user->isUser()
            && $participant->user->tenant_id === $session->tenant_id
        );
        $hasInjects = $session->injects->isNotEmpty();
        $allInjectsPending = $hasInjects
            && $session->injects->every(fn ($inject) => $inject->status === TtxSessionInjectStatus::Pending);
        $notStarted = $session->started_at === null;
        $hasExerciseSnapshot = is_array($session->exercise_snapshot)
            && filled($session->exercise_snapshot['title'] ?? null)
            && filled($session->exercise_snapshot['scenario'] ?? null);
        $hasPlaybook = $session->playbook_id !== null
            && is_array($session->playbook_snapshot)
            && filled($session->playbook_snapshot['title'] ?? null)
            && (filled($session->playbook_snapshot['content'] ?? null)
                || is_array($session->playbook_snapshot['structured_phases'] ?? null));

        // Structured V2 sessions derive mandatory ownership by intersecting the
        // Scenario capabilities with each immutable Playbook phase's capability
        // associations. Irrelevant phases are intentionally excluded.
        $relevantPhaseKeys = PlaybookPhaseStructure::relevantKeys($session->playbook_snapshot, $session->exercise_snapshot);
        $structuredResponsibilityMode = $session->response_contract_version >= 2 && $relevantPhaseKeys !== [];
        $primaryAssignments = $session->responsibilityAssignments
            ->where('role', 'primary')
            ->groupBy('playbook_phase_key');
        $responsibilityCoverageComplete = $structuredResponsibilityMode
            && collect($relevantPhaseKeys)->every(function (string $phaseKey) use ($primaryAssignments, $session): bool {
                $owners = $primaryAssignments->get($phaseKey, collect());
                if ($owners->count() !== 1) {
                    return false;
                }

                $team = $session->teams->firstWhere('id', $owners->first()->session_team_id);

                return $team !== null && $team->participants->isNotEmpty();
            });
        $uncoveredRelevantPhaseKeys = $structuredResponsibilityMode
            ? collect($relevantPhaseKeys)->reject(function (string $phaseKey) use ($primaryAssignments, $session): bool {
                $owners = $primaryAssignments->get($phaseKey, collect());
                if ($owners->count() !== 1) {
                    return false;
                }

                $team = $session->teams->firstWhere('id', $owners->first()->session_team_id);

                return $team !== null && $team->participants->isNotEmpty();
            })->values()->all()
            : [];
        $legacyPreparationComplete = $session->teams
            ->filter(fn (TtxSessionTeam $team) => $team->participants->isNotEmpty())
            ->every(fn (TtxSessionTeam $team) => mb_strlen(trim((string) $team->responsibilities)) >= 10);
        $participatingTeamsPrepared = $structuredResponsibilityMode
            ? $responsibilityCoverageComplete
            : $legacyPreparationComplete;

        $readiness = [
            'has_exercise_snapshot' => $hasExerciseSnapshot,
            'has_playbook' => $hasPlaybook,
            'has_facilitator' => $hasFacilitator,
            'has_teams' => $hasTeams,
            'has_non_facilitator_participant' => $hasParticipant,
            'participants_have_valid_team' => $participantsValid,
            'participating_teams_prepared' => $hasParticipant && $participatingTeamsPrepared,
            'uses_structured_responsibility_ownership' => $structuredResponsibilityMode,
            'responsibility_coverage_complete' => $structuredResponsibilityMode ? $responsibilityCoverageComplete : null,
            'relevant_playbook_phase_keys' => $relevantPhaseKeys,
            'uncovered_relevant_phase_keys' => $uncoveredRelevantPhaseKeys,
            'has_injects' => $hasInjects,
            'all_injects_pending' => $allInjectsPending,
            // Retained for the P2 preparation contract.
            'has_inject' => $hasInjects,
            'exactly_one_facilitator' => $hasFacilitator,
            'has_participants' => $hasParticipant,
            'not_started' => $notStarted,
        ];
        $readiness['ready'] = $readiness['has_exercise_snapshot']
            && $hasPlaybook
            && $hasFacilitator
            && $hasTeams
            && $hasParticipant
            && $participantsValid
            && $participatingTeamsPrepared
            && $hasInjects
            && $allInjectsPending
            && $notStarted;
        $readiness['can_mark_ready'] = $session->status === TtxSessionStatus::Draft
            && $readiness['ready'];

        return $readiness;
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

    public function advanceInject(TtxSession $session, User $actor, bool $confirmIncomplete = false): TtxSessionInject
    {
        $this->assertRuntimeActor($actor, $session);
        if ($session->status !== TtxSessionStatus::InProgress) {
            throw ValidationException::withMessages(['session' => 'Inject hanya dapat dijalankan saat sesi berlangsung.']);
        }

        return DB::transaction(function () use ($session, $actor, $confirmIncomplete) {
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

            $responses = TtxSessionResponse::query()
                ->where('session_id', $session->id)
                ->where('session_inject_id', $current->id)
                ->lockForUpdate()
                ->get();

            $teamCount = $session->teams()->count();
            $respondedTeamIds = $responses
                ->filter(fn (TtxSessionResponse $response) => trim($response->decision) !== '' && $response->session_team_id !== null)
                ->pluck('session_team_id')
                ->unique();
            $hasAttributableLegacyResponse = $teamCount === 1
                && $responses->contains(fn (TtxSessionResponse $response) => $response->session_team_id === null && trim($response->decision) !== '');
            $respondedCount = $respondedTeamIds->count() + ($hasAttributableLegacyResponse ? 1 : 0);
            if ($respondedCount < $teamCount && ! $confirmIncomplete) {
                throw ValidationException::withMessages([
                    'responses' => sprintf(
                        '%d dari %d tim belum mengirim respons. Konfirmasi diperlukan untuk melanjutkan dan mengunci inject ini.',
                        $teamCount - $respondedCount,
                        $teamCount
                    ),
                ]);
            }

            // Lock next PENDING inject
            $next = $session->injects()->where('status', TtxSessionInjectStatus::Pending)->lockForUpdate()->first();

            // Update current inject → LOCKED
            $current->update(['status' => TtxSessionInjectStatus::Locked, 'locked_at' => now()]);
            Audit::log('ttx.inject_locked', $current, ['session_id' => $session->id]);

            // Lock all submitted team responses; teams without one remain no-response.
            DB::table('ttx_session_responses')
                ->where('session_id', $session->id)
                ->where('session_inject_id', $current->id)
                ->update(['locked_at' => now()]);
            foreach ($responses as $response) {
                Audit::log('ttx.response_locked', $response, [
                    'session_id' => $session->id,
                    'session_inject_id' => $current->id,
                    'session_team_id' => $response->session_team_id,
                    'response_id' => $response->id,
                    'actor_id' => $actor->id,
                    'revision' => $response->revision,
                ]);
            }

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
        $actor?->loadMissing('team.responsibilityAssignments');
        $facilitator = $session->created_by === $user->id && $user->isTenantAdmin();
        abort_unless($facilitator || $actor !== null, 403);
        $session->loadMissing(['facilitator:id,name', 'teams.participants.user:id,name,is_active', 'teams.responsibilityAssignments']);
        $injects = $session->injects()->get();
        if (! $facilitator) {
            $injects = $injects->whereIn('status', [TtxSessionInjectStatus::Active, TtxSessionInjectStatus::Locked]);
        }
        $current = $injects->firstWhere('status', TtxSessionInjectStatus::Active);

        // Pending/future injects never expose response data. Participants receive only
        // their own team's response; facilitators receive every team's read-only status.
        $responseableIds = $injects
            ->whereIn('status', [TtxSessionInjectStatus::Active, TtxSessionInjectStatus::Locked])
            ->pluck('id')
            ->all();
        $responseQuery = TtxSessionResponse::query()
            ->where('session_id', $session->id)
            ->whereIn('session_inject_id', $responseableIds);
        if (! $facilitator) {
            abort_unless($actor?->team_id !== null, 403);
            $singleTeamSession = $session->teams->count() === 1;
            $responseQuery->where(function ($query) use ($actor, $singleTeamSession) {
                $query->where('session_team_id', $actor->team_id);
                if ($singleTeamSession) {
                    $query->orWhereNull('session_team_id');
                }
            });
        }
        $responses = $responseQuery->get()->groupBy('session_inject_id');
        $safeEditorNames = $this->safeEditorNames($session);
        $visibleTeams = $facilitator
            ? $session->teams
            : $session->teams->where('id', $actor?->team_id);

        $payload = [
            'id' => $session->id,
            'title' => $session->title,
            'status' => $session->status->value,
            'response_contract_version' => $session->response_contract_version,
            'scheduled_at' => $session->scheduled_at?->toISOString(),
            'started_at' => $session->started_at?->toISOString(),
            'completed_at' => $session->completed_at?->toISOString(),
            'duration_minutes' => $session->started_at
                ? (int) $session->started_at->diffInMinutes($session->completed_at ?? now())
                : null,
            'actor_role' => $facilitator ? 'facilitator' : 'participant',
            'scenario' => is_array($session->exercise_snapshot) ? ($session->exercise_snapshot['title'] ?? null) : null,
            'scenario_snapshot' => collect($session->exercise_snapshot ?? [])->only(['title', 'scenario', 'objectives', 'scope', 'capability_codes'])->all(),
            'capabilities' => $this->capabilitiesForSnapshot($session->exercise_snapshot),
            'playbook' => $facilitator
                ? $this->safePlaybookSnapshot($session->playbook_snapshot)
                : $this->safeParticipantPlaybookReference($session->playbook_snapshot, $session->exercise_snapshot, $actor?->team),
            'facilitator_name' => $session->facilitator?->name,
            'participant_team' => $actor?->team ? [
                'id' => $actor->team->id,
                'name' => $actor->team->name,
                'responsibilities' => $actor->team->responsibilities,
                'responsibility_assignments' => $actor->team->responsibilityAssignments->map(fn (TtxSessionResponsibilityAssignment $assignment) => [
                    'playbook_phase_key' => $assignment->playbook_phase_key,
                    'role' => $assignment->role,
                ])->values(),
            ] : null,
            'progress' => [
                'current' => $current?->order,
                'total' => $facilitator ? $session->injects()->count() : $injects->count(),
            ],
            'participant_count' => $session->participants()->count(),
            'teams' => $visibleTeams->map(fn (TtxSessionTeam $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'responsibilities' => $team->responsibilities,
                'responsibility_assignments' => $team->responsibilityAssignments->map(fn (TtxSessionResponsibilityAssignment $assignment) => [
                    'playbook_phase_key' => $assignment->playbook_phase_key,
                    'role' => $assignment->role,
                ])->values(),
                'participants' => $team->participants->map(fn (TtxSessionParticipant $participant) => [
                    ...($facilitator ? ['id' => $participant->user_id] : []),
                    'name' => $participant->user->name,
                ])->values(),
            ])->values(),
            'injects' => $injects->map(function ($i) use ($responses, $facilitator, $session, $safeEditorNames) {
                $payload = [
                    'id' => $i->id,
                    'order' => $i->order,
                    'status' => $i->status->value,
                    'snapshot' => $facilitator
                        ? $i->inject_snapshot
                        : $this->safeParticipantInjectSnapshot($i->inject_snapshot),
                ];

                if ($i->status !== TtxSessionInjectStatus::Pending) {
                    $injectResponses = $responses->get($i->id, collect());
                    if ($facilitator) {
                        $byTeam = $injectResponses->whereNotNull('session_team_id')->keyBy('session_team_id');
                        $legacy = $injectResponses->firstWhere('session_team_id', null);
                        if ($legacy && $session->teams->count() === 1 && ! $byTeam->has($session->teams->first()->id)) {
                            $byTeam->put($session->teams->first()->id, $legacy);
                        }
                        $payload['team_responses'] = $session->teams->map(fn (TtxSessionTeam $team) => [
                            'team' => ['id' => $team->id, 'name' => $team->name],
                            'response' => $this->safeResponsePayload($byTeam->get($team->id), $safeEditorNames),
                        ])->values();
                        $payload['response_summary'] = [
                            'responded' => $byTeam->count(),
                            'total' => $session->teams->count(),
                        ];
                        if ($session->teams->count() === 1) {
                            $payload['response'] = $this->safeResponsePayload($byTeam->get($session->teams->first()->id), $safeEditorNames);
                        } elseif ($legacy) {
                            $payload['legacy_response'] = $this->safeResponsePayload($legacy, $safeEditorNames);
                        }
                    } else {
                        $payload['response'] = $this->safeResponsePayload($injectResponses->first(), $safeEditorNames);
                    }
                }

                return $payload;
            })->values(),
        ];

        if (! $facilitator && $session->status === TtxSessionStatus::Completed) {
            $summary = $session->afterActionSummary()->first();
            $payload['outcome'] = $summary ? [
                'overall_summary' => $summary->overall_summary,
                'strengths' => $summary->strengths,
                'improvement_areas' => $summary->improvement_areas,
                'key_lessons' => $summary->key_lessons,
            ] : null;
        }

        return $payload;
    }

    public function debriefReadModel(User $actor, TtxSession $session): array
    {
        $this->assertDebriefActor($actor, $session);
        abort_unless(in_array($session->status, [TtxSessionStatus::Debrief, TtxSessionStatus::Completed], true), 409);

        $evaluation = $session->evaluations()->get()->keyBy('dimension');
        $summary = $session->afterActionSummary()->first();
        $dimensions = [];
        $requiredDimensions = $this->requiredEvaluationDimensions($session);
        foreach (self::EVALUATION_DIMENSIONS as $dimension => $definition) {
            $entry = $evaluation->get($dimension);
            $dimensions[] = [
                'dimension' => $dimension,
                ...$definition,
                'rating' => $entry?->rating,
                'evidence' => $entry?->evidence,
                'finding' => $entry?->finding,
                'required' => in_array($dimension, $requiredDimensions, true),
                'has_evaluation' => $entry !== null,
            ];
        }
        $ratingOptions = [];
        foreach (self::EVALUATION_RATINGS as $value => $label) {
            $ratingOptions[] = ['value' => $value, 'label' => $label];
        }

        return [
            'session' => $this->readModel($actor, $session),
            'dimensions' => $dimensions,
            'rating_options' => $ratingOptions,
            'action_items' => $session->actionItems()->get()->map(fn (TtxActionItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'owner' => $item->owner,
                'priority' => $item->priority,
                'due_date' => $item->due_date?->format('Y-m-d'),
                'status' => $item->status,
                'capability_code' => $item->capability_code,
                'playbook_phase_key' => $item->playbook_phase_key,
                'category' => $item->category,
            ])->all(),
            'after_action_summary' => $summary ? [
                'overall_summary' => $summary->overall_summary,
                'strengths' => $summary->strengths,
                'improvement_areas' => $summary->improvement_areas,
                'key_lessons' => $summary->key_lessons,
            ] : null,
            'editable' => $session->status === TtxSessionStatus::Debrief,
        ];
    }

    public function updateEvaluation(User $actor, TtxSession $session, array $entries): void
    {
        $this->assertMutableDebrief($actor, $session);

        DB::transaction(function () use ($actor, $session, $entries) {
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->assertMutableDebrief($actor, $lockedSession);

            foreach ($entries as $entry) {
                $evaluation = TtxSessionEvaluation::query()
                    ->where('session_id', $lockedSession->id)
                    ->where('dimension', $entry['dimension'])
                    ->first() ?? new TtxSessionEvaluation;
                $evaluation->forceFill([
                    'tenant_id' => $lockedSession->tenant_id,
                    'session_id' => $lockedSession->id,
                    'dimension' => $entry['dimension'],
                    'rating' => $entry['rating'],
                    'evidence' => $entry['evidence'] ?? null,
                    // Older/partial clients may omit Finding. Explicit null still clears a draft.
                    'finding' => array_key_exists('finding', $entry) ? $entry['finding'] : $evaluation->finding,
                    'updated_by' => $actor->id,
                ])->save();
            }

            Audit::log('ttx.evaluation_updated', $lockedSession, [
                'dimensions' => collect($entries)->pluck('dimension')->values()->all(),
            ]);
        });
    }

    public function createActionItem(User $actor, TtxSession $session, array $data): TtxActionItem
    {
        $this->assertMutableDebrief($actor, $session);
        $data = $this->validatedActionItemSemantics($session, $data);

        return DB::transaction(function () use ($actor, $session, $data) {
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->assertMutableDebrief($actor, $lockedSession);
            $item = TtxActionItem::forceCreate([
                'tenant_id' => $lockedSession->tenant_id,
                'session_id' => $lockedSession->id,
                ...$data,
                'status' => $data['status'] ?? 'open',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            Audit::log('ttx.action_item_created', $item, ['session_id' => $lockedSession->id]);

            return $item;
        });
    }

    public function updateActionItem(User $actor, TtxSession $session, TtxActionItem $item, array $data): TtxActionItem
    {
        $this->assertMutableDebrief($actor, $session);
        abort_unless($item->session_id === $session->id && $item->tenant_id === $session->tenant_id, 404);
        $data = $this->validatedActionItemSemantics($session, [
            'capability_code' => $item->capability_code,
            'playbook_phase_key' => $item->playbook_phase_key,
            'category' => $item->category,
            ...$data,
        ]);

        return DB::transaction(function () use ($actor, $session, $item, $data) {
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->assertMutableDebrief($actor, $lockedSession);
            $lockedItem = TtxActionItem::query()
                ->where('session_id', $lockedSession->id)
                ->lockForUpdate()
                ->findOrFail($item->id);
            $wasCompleted = $lockedItem->status === 'completed';
            $lockedItem->forceFill([...$data, 'updated_by' => $actor->id])->save();
            $action = ! $wasCompleted && $lockedItem->status === 'completed'
                ? 'ttx.action_item_completed'
                : 'ttx.action_item_updated';
            Audit::log($action, $lockedItem, ['session_id' => $lockedSession->id]);

            return $lockedItem->fresh();
        });
    }

    public function updateAfterActionSummary(User $actor, TtxSession $session, array $data): TtxAfterActionSummary
    {
        $this->assertMutableDebrief($actor, $session);

        return DB::transaction(function () use ($actor, $session, $data) {
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->assertMutableDebrief($actor, $lockedSession);
            $summary = TtxAfterActionSummary::query()
                ->where('session_id', $lockedSession->id)
                ->first() ?? new TtxAfterActionSummary;
            $summary->forceFill([
                'tenant_id' => $lockedSession->tenant_id,
                'session_id' => $lockedSession->id,
                ...$data,
                'updated_by' => $actor->id,
            ])->save();
            Audit::log('ttx.aar_updated', $lockedSession);

            return $summary;
        });
    }

    public function complete(User $actor, TtxSession $session): TtxSession
    {
        $this->assertMutableDebrief($actor, $session);

        return DB::transaction(function () use ($actor, $session) {
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->assertMutableDebrief($actor, $lockedSession);

            $requiredDimensions = $this->requiredEvaluationDimensions($lockedSession);
            $evaluations = $lockedSession->evaluations()
                ->whereIn('dimension', $requiredDimensions)
                ->whereIn('rating', array_keys(self::EVALUATION_RATINGS))
                ->get()
                ->keyBy('dimension');
            if ($evaluations->count() !== count($requiredDimensions)) {
                throw ValidationException::withMessages([
                    'evaluation' => 'Lengkapi rating untuk seluruh dimensi evaluasi wajib sesi sebelum menyelesaikan sesi.',
                ]);
            }
            if ($lockedSession->response_contract_version >= 2
                && collect($requiredDimensions)->contains(fn (string $dimension) => trim((string) $evaluations->get($dimension)?->evidence) === ''
                    || trim((string) $evaluations->get($dimension)?->finding) === '')) {
                throw ValidationException::withMessages([
                    'evaluation' => 'Lengkapi evidence dan finding untuk seluruh capability exercise sebelum menyelesaikan sesi.',
                ]);
            }

            $summary = $lockedSession->afterActionSummary()->first();
            if (! $summary || collect(['overall_summary', 'strengths', 'improvement_areas', 'key_lessons'])
                ->contains(fn (string $field) => trim((string) $summary->{$field}) === '')) {
                throw ValidationException::withMessages([
                    'after_action_summary' => 'Lengkapi seluruh bagian After-Action Summary sebelum menyelesaikan sesi.',
                ]);
            }

            $lockedSession->update([
                'status' => TtxSessionStatus::Completed,
                'completed_at' => now(),
            ]);
            Audit::log('ttx.session_completed', $lockedSession);

            return $lockedSession->fresh();
        });
    }

    /**
     * Only expose released, participant-facing inject content. Snapshot JSON is
     * deliberately allow-listed so later facilitator/evaluation fields cannot
     * cross the participant boundary by accident.
     */
    private function safeParticipantInjectSnapshot(?array $snapshot): array
    {
        return collect($snapshot ?? [])->only([
            'title',
            'description',
            'situation',
            'known_facts',
            'discussion_prompt',
            'capability_codes',
        ])->all();
    }

    /**
     * Build a response payload exposing only frontend-required fields.
     * Returns null when no response exists for this inject.
     */
    private function safeResponsePayload(?TtxSessionResponse $response, array $safeEditorNames = []): ?array
    {
        if (! $response) {
            return null;
        }

        $editorId = $response->last_edited_by ?? $response->submitted_by;
        $lastEditedByName = $response->session_team_id !== null
            ? ($safeEditorNames[$response->session_team_id][$editorId] ?? null)
            : null;

        return [
            'id' => $response->id,
            'session_team_id' => $response->session_team_id,
            'decision' => $response->decision,
            'rationale' => $response->rationale,
            'owner' => $response->owner,
            'immediate_actions' => $response->immediate_actions,
            'coordination_handoff' => $response->coordination_handoff,
            'escalation' => $response->escalation,
            'unknowns' => $response->unknowns,
            'notes' => $response->notes,
            'revision' => $response->revision,
            'submitted_at' => $response->submitted_at->toISOString(),
            'saved_at' => ($response->updated_at ?? $response->submitted_at)->toISOString(),
            'last_edited_by_name' => $lastEditedByName,
            'last_edited_at' => ($response->updated_at ?? $response->submitted_at)->toISOString(),
            'locked_at' => $response->locked_at?->toISOString(),
        ];
    }

    public function participantResponseReadModel(User $actor, TtxSession $session, TtxSessionResponse $response): array
    {
        $this->assertResponseActor($actor, $session);
        $participant = $this->responseParticipant($actor, $session);
        abort_unless($response->session_id === $session->id && $response->session_team_id === $participant->team_id, 404);

        $session->loadMissing('teams.participants.user:id,name,is_active');

        return $this->safeResponsePayload($response, $this->safeEditorNames($session));
    }

    private function safeEditorNames(TtxSession $session): array
    {
        $names = [];
        foreach ($session->teams as $team) {
            foreach ($team->participants as $participant) {
                if ($participant->user?->is_active) {
                    $names[$team->id][$participant->user_id] = $participant->user->name;
                }
            }
        }

        return $names;
    }

    public function storeResponse(User $actor, TtxSession $session, int $injectId, array $data): TtxSessionResponse
    {
        $this->assertResponseActor($actor, $session);
        $this->validateResponseContract($session, $data);

        return DB::transaction(function () use ($actor, $session, $injectId, $data) {
            // Lock order: Session → Inject (compatible with start/advance)
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->assertResponseActor($actor, $lockedSession);
            $participant = $this->responseParticipant($actor, $lockedSession);

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
            $singleTeamSession = $lockedSession->teams()->count() === 1;
            if (TtxSessionResponse::query()
                ->where('session_inject_id', $injectId)
                ->where(function ($query) use ($participant, $singleTeamSession) {
                    $query->where('session_team_id', $participant->team_id);
                    if ($singleTeamSession) {
                        $query->orWhereNull('session_team_id');
                    }
                })
                ->exists()) {
                abort(409, 'Respons tim untuk inject ini sudah ada.');
            }

            // Guard against concurrent duplicate creation race at DB level
            try {
                $response = TtxSessionResponse::forceCreate([
                    'tenant_id' => $lockedSession->tenant_id,
                    'session_id' => $lockedSession->id,
                    'session_inject_id' => $injectId,
                    'session_team_id' => $participant->team_id,
                    'decision' => trim($data['decision']),
                    'rationale' => isset($data['rationale']) ? trim($data['rationale']) : null,
                    'owner' => $data['owner'] ?? null,
                    'immediate_actions' => isset($data['immediate_actions']) ? trim($data['immediate_actions']) : null,
                    'coordination_handoff' => isset($data['coordination_handoff']) ? trim($data['coordination_handoff']) : null,
                    'escalation' => $data['escalation'] ?? null,
                    'unknowns' => $data['unknowns'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'revision' => 1,
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                ]);
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'ttx_responses_one_per_inject_team')) {
                    abort(409, 'Respons tim untuk inject ini sudah ada.');
                }
                throw $e;
            }

            // Audit inside same transaction
            Audit::log('ttx.response_saved', $response, [
                'session_id' => $lockedSession->id,
                'session_inject_id' => $injectId,
                'session_team_id' => $participant->team_id,
                'response_id' => $response->id,
                'actor_id' => $actor->id,
                'revision' => 1,
            ]);

            return $response;
        });
    }

    public function updateResponse(User $actor, TtxSession $session, int $responseId, int $expectedRevision, array $data): TtxSessionResponse
    {
        $this->assertResponseActor($actor, $session);
        $this->validateResponseContract($session, $data);

        return DB::transaction(function () use ($actor, $session, $responseId, $expectedRevision, $data) {
            // Lock order: Session → Inject (compatible with start/advance)
            $lockedSession = TtxSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->assertResponseActor($actor, $lockedSession);
            $participant = $this->responseParticipant($actor, $lockedSession);

            if ($lockedSession->status !== TtxSessionStatus::InProgress) {
                throw ValidationException::withMessages([
                    'session' => 'Response hanya dapat diubah saat sesi berlangsung.',
                ]);
            }

            // Load response scoped to session (defense-in-depth)
            $singleTeamSession = $lockedSession->teams()->count() === 1;
            $response = TtxSessionResponse::where('id', $responseId)
                ->where('session_id', $session->id)
                ->where(function ($query) use ($participant, $singleTeamSession) {
                    $query->where('session_team_id', $participant->team_id);
                    if ($singleTeamSession) {
                        $query->orWhereNull('session_team_id');
                    }
                })
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
                ->where(function ($query) use ($participant, $singleTeamSession) {
                    $query->where('session_team_id', $participant->team_id);
                    if ($singleTeamSession) {
                        $query->orWhereNull('session_team_id');
                    }
                })
                ->where('revision', $expectedRevision)
                ->update([
                    'decision' => trim($data['decision']),
                    'rationale' => isset($data['rationale']) ? trim($data['rationale']) : null,
                    'owner' => $data['owner'] ?? null,
                    'immediate_actions' => isset($data['immediate_actions']) ? trim($data['immediate_actions']) : null,
                    'coordination_handoff' => isset($data['coordination_handoff']) ? trim($data['coordination_handoff']) : null,
                    'escalation' => $data['escalation'] ?? null,
                    'unknowns' => $data['unknowns'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'last_edited_by' => $actor->id,
                    'revision' => $expectedRevision + 1,
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                abort(409, 'Versi response tidak sesuai. Silakan muat ulang dan coba lagi.');
            }

            // Audit inside same transaction
            $fresh = TtxSessionResponse::findOrFail($responseId);
            Audit::log('ttx.response_saved', $fresh, [
                'session_id' => $lockedSession->id,
                'session_inject_id' => $response->session_inject_id,
                'session_team_id' => $response->session_team_id,
                'response_id' => $response->id,
                'actor_id' => $actor->id,
                'revision' => $fresh->revision,
            ]);

            return $fresh;
        });
    }

    private function validateResponseContract(TtxSession $session, array $data): void
    {
        $required = $session->response_contract_version >= 2
            ? ['decision', 'rationale', 'immediate_actions', 'coordination_handoff']
            : ['decision'];

        $errors = [];
        foreach ($required as $field) {
            if (! isset($data[$field]) || ! is_string($data[$field]) || trim($data[$field]) === '') {
                $errors[$field] = 'Field ini wajib diisi untuk kontrak response sesi.';
            } elseif (mb_strlen($data[$field]) > 10000) {
                $errors[$field] = 'Field ini tidak boleh lebih dari 10000 karakter.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function assertPreparationActor(User $actor, TtxSession $session): void
    {
        abort_unless(
            $actor->is_active
            && $actor->isTenantAdmin()
            && $actor->tenant_id === $session->tenant_id
            && $session->created_by === $actor->id,
            403
        );
    }

    private function assertRuntimeActor(User $actor, TtxSession $session): void
    {
        abort_unless(
            $actor->is_active
            && $actor->isTenantAdmin()
            && $actor->tenant_id === $session->tenant_id
            && $session->created_by === $actor->id,
            403
        );
    }

    private function assertDebriefActor(User $actor, TtxSession $session): void
    {
        $this->assertRuntimeActor($actor, $session);
    }

    private function assertMutableDebrief(User $actor, TtxSession $session): void
    {
        $this->assertDebriefActor($actor, $session);
        if ($session->status !== TtxSessionStatus::Debrief) {
            throw ValidationException::withMessages([
                'session' => 'Debrief hanya dapat diubah saat sesi berstatus debrief.',
            ]);
        }
    }

    private function exerciseSnapshot(TtxExercise $exercise): array
    {
        return [
            'title' => $exercise->title,
            'scenario' => $exercise->scenario,
            'objectives' => $exercise->objectives,
            'scope' => $exercise->scope,
            'capability_codes' => ExerciseCapabilityCatalog::normalize($exercise->capability_codes, 'exercise.capability_codes'),
        ];
    }

    private function playbookSnapshot(TtxPlaybook $playbook): array
    {
        return [
            'source_id' => $playbook->id,
            'title' => $playbook->title,
            'description' => $playbook->description,
            'content' => $playbook->content,
            'structured_phases' => PlaybookPhaseStructure::normalize($playbook->structured_phases, 'playbook.structured_phases'),
        ];
    }

    private function safePlaybookReference(?array $snapshot): ?array
    {
        if (! is_array($snapshot) || ! filled($snapshot['title'] ?? null)) {
            return null;
        }

        return collect($snapshot)->only(['title', 'description', 'content', 'structured_phases'])->all();
    }

    /** @return list<array{code: string, label: string, description: string}> */
    private function capabilitiesForSnapshot(?array $snapshot): array
    {
        $codes = is_array($snapshot['capability_codes'] ?? null)
            ? $snapshot['capability_codes']
            : [];

        return collect(ExerciseCapabilityCatalog::catalog())
            ->filter(fn (array $capability): bool => in_array($capability['code'], $codes, true))
            ->values()
            ->all();
    }

    private function safeParticipantPlaybookReference(?array $snapshot, ?array $exerciseSnapshot, ?TtxSessionTeam $team): ?array
    {
        if (! is_array($snapshot) || ! filled($snapshot['title'] ?? null)) {
            return null;
        }

        $relevantKeys = PlaybookPhaseStructure::relevantKeys($snapshot, $exerciseSnapshot);
        $roles = $team?->responsibilityAssignments
            ->keyBy('playbook_phase_key')
            ->map(fn (TtxSessionResponsibilityAssignment $assignment): string => $assignment->role)
            ->all() ?? [];
        $phases = collect(is_array($snapshot['structured_phases'] ?? null) ? $snapshot['structured_phases'] : [])
            ->filter(fn ($phase): bool => is_array($phase)
                && in_array($phase['key'] ?? null, $relevantKeys, true))
            ->map(fn (array $phase): array => [
                'key' => $phase['key'],
                'title' => $phase['title'],
                'participant_summary' => $phase['participant_summary'],
                'capability_codes' => $phase['capability_codes'],
                'team_role' => $roles[$phase['key']] ?? null,
            ])->values()->all();

        return [
            'title' => $snapshot['title'],
            'description' => $snapshot['description'] ?? null,
            'structured_phases' => $phases,
            'legacy_reference' => $phases === [],
        ];
    }

    private function safePlaybookSnapshot(?array $snapshot): ?array
    {
        if (! is_array($snapshot) || ! filled($snapshot['title'] ?? null)) {
            return null;
        }

        return collect($snapshot)->only(['title', 'description', 'content', 'structured_phases'])->all();
    }

    /** @return list<string> */
    private function requiredEvaluationDimensions(TtxSession $session): array
    {
        if ($session->response_contract_version < 2) {
            return array_keys(self::EVALUATION_DIMENSIONS);
        }

        $codes = ExerciseCapabilityCatalog::normalize(
            is_array($session->exercise_snapshot['capability_codes'] ?? null)
                ? $session->exercise_snapshot['capability_codes']
                : null,
            'exercise_snapshot.capability_codes'
        );
        if ($codes === null || $codes === []) {
            return array_keys(self::EVALUATION_DIMENSIONS);
        }

        return collect($codes)
            ->map(fn (string $code) => ExerciseCapabilityCatalog::dimensionFor($code))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function validatedActionItemSemantics(TtxSession $session, array $data): array
    {
        $data = collect($data)->only([
            'title',
            'owner',
            'priority',
            'due_date',
            'status',
            'capability_code',
            'playbook_phase_key',
            'category',
        ])->all();

        if (array_key_exists('capability_code', $data) && $data['capability_code'] !== null) {
            $data['capability_code'] = ExerciseCapabilityCatalog::normalize([$data['capability_code']], 'capability_code')[0];
            if ($session->response_contract_version >= 2
                && ! in_array($data['capability_code'], ExerciseCapabilityCatalog::normalize(
                    is_array($session->exercise_snapshot['capability_codes'] ?? null) ? $session->exercise_snapshot['capability_codes'] : null,
                    'exercise_snapshot.capability_codes'
                ) ?? [], true)) {
                throw ValidationException::withMessages(['capability_code' => 'Capability tidak relevan dengan snapshot exercise sesi.']);
            }
        }

        if (array_key_exists('playbook_phase_key', $data) && $data['playbook_phase_key'] !== null) {
            $phaseKey = trim((string) $data['playbook_phase_key']);
            if (! in_array($phaseKey, PlaybookPhaseStructure::keysFromSnapshot($session->playbook_snapshot), true)) {
                throw ValidationException::withMessages(['playbook_phase_key' => 'Playbook phase tidak tersedia pada snapshot sesi.']);
            }
            $data['playbook_phase_key'] = $phaseKey;
        }

        if (array_key_exists('category', $data)
            && $data['category'] !== null
            && ! in_array($data['category'], ['corrective_action', 'playbook_improvement'], true)) {
            throw ValidationException::withMessages(['category' => 'Kategori action item tidak valid.']);
        }

        return $data;
    }

    /**
     * Convert the established three-section inject copy into explicit,
     * participant-safe runtime fields while retaining the immutable source.
     *
     * @return array{situation: string, known_facts: array<int, string>, discussion_prompt: string}
     */
    private function participantInjectContent(?string $description): array
    {
        $description ??= '';
        $pattern = '/Situasi:\s*(.*?)\s*Fakta yang diketahui:\s*(.*?)\s*Pertanyaan diskusi:\s*(.*)/si';
        if (preg_match($pattern, $description, $matches) !== 1) {
            return [
                'situation' => trim($description),
                'known_facts' => [],
                'discussion_prompt' => '',
            ];
        }

        $facts = preg_split('/\R\s*-\s*/u', trim($matches[2])) ?: [];

        return [
            'situation' => trim($matches[1]),
            'known_facts' => collect($facts)->map(fn (string $fact) => trim($fact, " \t\n\r\0\x0B-"))->filter()->values()->all(),
            'discussion_prompt' => trim($matches[3]),
        ];
    }

    private function assertResponseActor(User $actor, TtxSession $session): void
    {
        abort_unless(
            $actor->is_active
                && $actor->isUser()
                && $actor->tenant_id !== null
                && $actor->tenant_id === $session->tenant_id,
            403
        );
        abort_unless(
            $session->participants()
                ->where('tenant_id', $session->tenant_id)
                ->where('user_id', $actor->id)
                ->whereNotNull('team_id')
                ->whereHas('team', fn ($query) => $query
                    ->where('tenant_id', $session->tenant_id)
                    ->where('session_id', $session->id))
                ->exists(),
            403
        );
    }

    private function responseParticipant(User $actor, TtxSession $session): TtxSessionParticipant
    {
        $participant = $session->participants()
            ->where('tenant_id', $session->tenant_id)
            ->where('user_id', $actor->id)
            ->whereNotNull('team_id')
            ->whereHas('team', fn ($query) => $query
                ->where('tenant_id', $session->tenant_id)
                ->where('session_id', $session->id))
            ->first();

        abort_unless($participant !== null, 403);

        return $participant;
    }
}
