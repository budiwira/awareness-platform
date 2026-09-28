<?php

namespace App\Console\Commands;

use App\Enums\TtxSessionInjectStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxPlaybook;
use App\Models\User;
use App\Services\TtxSessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TtxE2eFixture extends Command
{
    protected $signature = 'ttx:e2e-fixture';

    protected $description = 'Create deterministic E2E fixture data for Playwright facilitator console tests';

    public function handle(): int
    {
        // ── SAFETY GUARD ──────────────────────────────────────────
        $env = app()->environment();
        $dbName = config('database.connections.'.config('database.default').'.database');

        if (! in_array($env, ['e2e', 'testing'])) {
            $this->error("❌ SAFETY REFUSAL: APP_ENV='{$env}' is not 'e2e' or 'testing'.");
            $this->error('   This command must only run against the awareness_e2e database.');

            return static::FAILURE;
        }

        if ($dbName !== 'awareness_e2e') {
            $this->error("❌ SAFETY REFUSAL: DB_DATABASE='{$dbName}' is not 'awareness_e2e'.");
            $this->error('   This command must only run against the awareness_e2e database.');

            return static::FAILURE;
        }

        $this->info("✅ Safety guard passed: env={$env}, db={$dbName}");

        // ── MIGRATE FRESH ─────────────────────────────────────────
        // awareness_e2e must already exist with correct privileges.
        // If the database or user permissions are missing, Artisan will fail here.
        $this->info('Running migrate:fresh on awareness_e2e...');
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->info(Artisan::output());

        // ── BASE SEEDERS ──────────────────────────────────────────
        // DatabaseSeeder creates acme/beta tenants, superadmin@platform.local, admin/user users.
        // This ensures existing E2E specs (which expect these users) continue to work.
        $this->info('Seeding base data...');
        Artisan::call('db:seed', ['--force' => true]);
        $this->info(Artisan::output());

        // ── E2E TENANT ────────────────────────────────────────────
        $tenant = Tenant::create([
            'name' => 'E2E Test Tenant',
            'slug' => 'e2e',
            'status' => 'active',
        ]);
        $this->info("E2E tenant created: id={$tenant->id}");

        // ── E2E USERS ─────────────────────────────────────────────
        // The session creator is the Tenant Admin facilitator.
        $facilitator = User::create([
            'name' => 'E2E Facilitator',
            'email' => 'facilitator@e2e.local',
            'password' => 'password',
            'role' => UserRole::TenantAdmin,
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        $this->info("Facilitator created: id={$facilitator->id}");

        // A second admin remains available for authorization checks.
        $admin = User::create([
            'name' => 'E2E Admin',
            'email' => 'admin@e2e.local',
            'password' => 'password',
            'role' => UserRole::TenantAdmin,
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        $this->info("Admin created: id={$admin->id}");

        // Participant user (for conflict/dirty sessions)
        $participant = User::create([
            'name' => 'E2E Participant',
            'email' => 'participant@e2e.local',
            'password' => 'password',
            'role' => UserRole::User,
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        $this->info("Participant created: id={$participant->id}");

        // ── EXERCISE + INJECTS ────────────────────────────────────
        $playbook = TtxPlaybook::create([
            'tenant_id' => $tenant->id,
            'title' => 'E2E Response Playbook',
            'content' => 'Validate the incident and coordinate the response.',
            'is_active' => true,
        ]);
        $exercise = TtxExercise::create([
            'tenant_id' => $tenant->id,
            'title' => 'E2E Phishing Response',
            'scenario' => 'Simulated phishing attack targeting the organization.',
            'objectives' => 'Test incident response coordination.',
            'scope' => 'Organization-wide.',
            'playbook_id' => $playbook->id,
            'phase' => 'planning',
        ]);

        $injects = collect();
        foreach ([1, 2, 3] as $order) {
            $injects->push(TtxInject::create([
                'tenant_id' => $tenant->id,
                'exercise_id' => $exercise->id,
                'order' => $order,
                'title' => "Inject {$order}: Scenario Update",
                'description' => "Inject {$order} description for E2E testing.",
            ]));
        }
        $this->info('Exercise + 3 injects created');

        // ── SERVICE INSTANCE ──────────────────────────────────────
        $service = app(TtxSessionService::class);

        // ── SESSION A: READY ──────────────────────────────────────
        $readySession = $service->create($facilitator, $exercise, 'E2E Ready Session');
        $readyTeam = $service->createTeam($facilitator, $readySession, 'Security');
        $service->updateTeamResponsibilities($facilitator, $readySession, $readyTeam->id, 'Coordinate the security response.');
        $service->assignParticipant($facilitator, $readySession, $participant, $readyTeam);
        $readySession = $service->markReady($facilitator, $readySession);
        $this->info("Ready session created: id={$readySession->id}");

        // ── SESSION B: CONFLICT ───────────────────────────────────
        // State: in_progress, 1 active inject with an official response (revision 1)
        // Used by two browser contexts to produce a stale-revision 409.
        $conflictSession = $service->create($facilitator, $exercise, 'E2E Conflict Session');
        $conflictTeam = $service->createTeam($facilitator, $conflictSession, 'Security');
        $service->updateTeamResponsibilities($facilitator, $conflictSession, $conflictTeam->id, 'Coordinate the security response.');
        $service->assignParticipant($facilitator, $conflictSession, $participant, $conflictTeam);
        $conflictSession = $service->markReady($facilitator, $conflictSession);
        // Only the creator Tenant Admin starts the session.
        $conflictSession = $service->start($facilitator, $conflictSession);

        $conflictActiveInject = $conflictSession->injects()
            ->where('status', TtxSessionInjectStatus::Active)
            ->first();

        $service->storeResponse($participant, $conflictSession, $conflictActiveInject->id, [
            'decision' => 'Contain the phishing email immediately.',
            'rationale' => 'Quick containment limits exposure.',
            'owner' => 'Security Team',
            'immediate_actions' => 'Block sender domain, notify users.',
            'coordination_handoff' => 'Security coordinates with IT operations.',
            'escalation' => 'CISO',
            'unknowns' => 'Number of affected users.',
            'notes' => 'Initial response for E2E conflict test.',
        ]);
        $this->info("Conflict session created: id={$conflictSession->id}, inject={$conflictActiveInject->id}");

        // ── SESSION C: DIRTY ──────────────────────────────────────
        // State: in_progress, 1 locked inject (with response) + 1 active inject
        // Used to test dirty-state tracking when switching between injects.
        $dirtySession = $service->create($facilitator, $exercise, 'E2E Dirty Session');
        $dirtyTeam = $service->createTeam($facilitator, $dirtySession, 'Security');
        $service->updateTeamResponsibilities($facilitator, $dirtySession, $dirtyTeam->id, 'Coordinate the security response.');
        $service->assignParticipant($facilitator, $dirtySession, $participant, $dirtyTeam);
        $dirtySession = $service->markReady($facilitator, $dirtySession);
        // Only the creator Tenant Admin starts the session.
        $dirtySession = $service->start($facilitator, $dirtySession);

        // First inject is active — create a response
        $dirtyFirstInject = $dirtySession->injects()
            ->where('status', TtxSessionInjectStatus::Active)
            ->first();

        $service->storeResponse($participant, $dirtySession, $dirtyFirstInject->id, [
            'decision' => 'Escalate to management immediately.',
            'rationale' => 'Severity warrants escalation.',
            'owner' => 'Management',
            'immediate_actions' => 'Brief CISO and legal.',
            'coordination_handoff' => 'Security coordinates with management.',
            'escalation' => 'Board',
            'unknowns' => 'Full scope of breach.',
            'notes' => 'Initial response for E2E dirty form test.',
        ]);

        // Advance to next inject (locks first inject + response, activates second)
        $service->advanceInject($dirtySession, $facilitator);

        $dirtySession->refresh();
        $dirtyActiveInject = $dirtySession->injects()
            ->where('status', TtxSessionInjectStatus::Active)
            ->first();

        $this->info("Dirty session created: id={$dirtySession->id}, locked={$dirtyFirstInject->id}, active={$dirtyActiveInject->id}");

        // ── WRITE METADATA ────────────────────────────────────────
        $metadataPath = base_path('tests/e2e/.fixtures/ttx.json');
        $metadataDir = dirname($metadataPath);

        if (! is_dir($metadataDir)) {
            mkdir($metadataDir, 0755, true);
        }

        $metadata = [
            'readySessionId' => (int) $readySession->id,
            'conflictSessionId' => (int) $conflictSession->id,
            'dirtySessionId' => (int) $dirtySession->id,
            'conflictActiveInjectId' => (int) $conflictActiveInject->id,
            'dirtyActiveInjectId' => (int) $dirtyActiveInject->id,
            'tenantSlug' => 'e2e',
        ];

        file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info('Metadata written to: '.$metadataPath);
        $this->info(json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return static::SUCCESS;
    }
}
