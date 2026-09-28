<?php

use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxPlaybook;
use App\Models\User;
use App\Services\TtxSessionService;
use Database\Seeders\TtxContentSeeder;
use Database\Seeders\TtxFlagshipScenarioSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

function seedFlagshipFixture(): array
{
    $tenant = Tenant::factory()->create([
        'name' => 'PT Demo Nusantara',
        'slug' => 'pt-demo',
    ]);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

    (new TtxContentSeeder)->run();

    $exercise = TtxExercise::query()
        ->where('tenant_id', $tenant->id)
        ->where('title', TtxContentSeeder::FLAGSHIP_TITLE)
        ->firstOrFail();

    return [$tenant, $admin, $exercise];
}

test('flagship exercise is seeded as planning content with five participant-safe ordered injects', function () {
    [$tenant, , $exercise] = seedFlagshipFixture();
    $injects = $exercise->injects()->get();
    $playbook = $exercise->playbook;

    expect($exercise->title)->toBe('Credential Compromise & Coordinated Incident Response')
        ->and($exercise->tenant_id)->toBe($tenant->id)
        ->and($exercise->phase)->toBe('planning')
        ->and($playbook)->not->toBeNull()
        ->and($playbook->title)->toBe(TtxContentSeeder::FLAGSHIP_PLAYBOOK_TITLE)
        ->and($playbook->content)->toContain('Detection & Validation', 'Post-Incident Review')
        ->and(collect($playbook->structured_phases)->pluck('key')->all())->toBe([
            'detection_validation',
            'incident_classification',
            'escalation_ownership',
            'account_containment',
            'session_revocation',
            'impact_investigation',
            'incident_communication',
            'recovery',
            'post_incident_review',
        ])
        ->and(collect($playbook->structured_phases)->every(
            fn (array $phase) => filled($phase['guidance'] ?? null)
                && filled($phase['participant_summary'] ?? null)
                && ($phase['capability_codes'] ?? []) !== []
        ))->toBeTrue()
        ->and($exercise->capability_codes)->toBe(['EX-1', 'EX-2', 'EX-3', 'EX-4', 'EX-5', 'EX-6'])
        ->and($exercise->scope)->toContain('Beginner–Intermediate', '45–60 menit', '4–8 peserta', '1 fasilitator')
        ->and($exercise->scenario)->not->toContain('pemasok', 'rumor', 'enam jam')
        ->and($injects)->toHaveCount(5)
        ->and($injects->pluck('order')->all())->toBe([1, 2, 3, 4, 5])
        ->and($injects->pluck('title')->all())->toBe([
            'Suspicious Account Activity',
            'Confirmed Credential Compromise',
            'Lateral Business Impact',
            'External and Internal Communication Pressure',
            'Recovery and Lessons',
        ]);

    foreach ($injects as $inject) {
        expect($inject->tenant_id)->toBe($tenant->id)
            ->and($inject->description)->toContain('Situasi:', 'Fakta yang diketahui:', 'Pertanyaan diskusi:')
            ->and($inject->description)->not->toContain('facilitator_notes', 'password=', 'api_key');
    }

    expect($injects->mapWithKeys(fn ($inject) => [$inject->order => $inject->capability_codes])->all())
        ->toBe(TtxContentSeeder::FLAGSHIP_OBJECTIVE_MAP);

    expect(collect(TtxContentSeeder::FLAGSHIP_OBJECTIVE_MAP)->flatten()->unique()->sort()->values()->all())
        ->toBe(['EX-1', 'EX-2', 'EX-3', 'EX-4', 'EX-5', 'EX-6']);
});

test('flagship seeding is idempotent and preserves its original tenant association', function () {
    [$tenant, , $exercise] = seedFlagshipFixture();
    $otherTenant = Tenant::factory()->create(['slug' => 'beta']);

    (new TtxContentSeeder)->run();

    expect(TtxExercise::query()->where('title', TtxContentSeeder::FLAGSHIP_TITLE)->count())->toBe(1)
        ->and(TtxPlaybook::query()->where('title', TtxContentSeeder::FLAGSHIP_PLAYBOOK_TITLE)->count())->toBe(1)
        ->and(TtxExercise::query()->whereKey($exercise->id)->value('tenant_id'))->toBe($tenant->id)
        ->and(TtxExercise::query()->where('tenant_id', $otherTenant->id)->where('title', TtxContentSeeder::FLAGSHIP_TITLE)->exists())->toBeFalse()
        ->and($exercise->fresh()->injects()->count())->toBe(5)
        ->and($exercise->fresh()->injects()->pluck('order')->all())->toBe([1, 2, 3, 4, 5]);
});

test('demo tenant receives the flagship while unrelated private scenarios remain isolated', function () {
    $demo = Tenant::factory()->create(['name' => 'PT Demo Nusantara', 'slug' => 'pt-demo']);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $demo->id]);
    $privateTenant = Tenant::factory()->create(['name' => 'Private Tenant', 'slug' => 'private-tenant']);
    $privateScenario = TtxExercise::create([
        'tenant_id' => $privateTenant->id,
        'title' => 'Private Legal Incident',
        'scenario' => 'Tenant confidential content',
        'phase' => 'planning',
    ]);
    TtxInject::create([
        'tenant_id' => $privateTenant->id,
        'exercise_id' => $privateScenario->id,
        'order' => 1,
        'title' => 'Private Inject',
        'description' => 'Confidential tenant-only content',
    ]);

    (new TtxFlagshipScenarioSeeder)->run();
    (new TtxFlagshipScenarioSeeder)->run();

    $flagship = TtxExercise::query()
        ->where('tenant_id', $demo->id)
        ->where('title', TtxContentSeeder::FLAGSHIP_TITLE)
        ->sole();

    expect($flagship->injects()->pluck('title')->all())->toBe([
        'Suspicious Account Activity',
        'Confirmed Credential Compromise',
        'Lateral Business Impact',
        'External and Internal Communication Pressure',
        'Recovery and Lessons',
    ])->and(TtxExercise::query()
        ->where('tenant_id', $demo->id)
        ->where('title', TtxContentSeeder::FLAGSHIP_TITLE)
        ->count())->toBe(1)
        ->and(TtxExercise::query()
            ->where('tenant_id', $privateTenant->id)
            ->where('title', TtxContentSeeder::FLAGSHIP_TITLE)
            ->exists())->toBeFalse();

    $response = $this->actingAs($admin)
        ->get(route('tenant.ttx.sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Ttx/Sessions/Index')
            ->has('scenarios', 1)
            ->where('scenarios.0.title', TtxContentSeeder::FLAGSHIP_TITLE));

    expect($response->getContent())
        ->not->toContain('Private Legal Incident')
        ->not->toContain('Confidential tenant-only content');

    $platformAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($platformAdmin)
        ->get(route('platform.ttx.scenarios.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Ttx/Scenarios/Index')
            ->has('scenarios', 2)
            ->where('scenarios', fn ($scenarios) => collect($scenarios)->pluck('title')->contains(TtxContentSeeder::FLAGSHIP_TITLE)
                && collect($scenarios)->pluck('title')->contains('Private Legal Incident')));
});

test('flagship exercise creates complete runtime snapshots and keeps future injects confidential', function () {
    [$tenant, $admin, $exercise] = seedFlagshipFixture();
    $participant = User::factory()->create(['tenant_id' => $tenant->id]);
    $service = app(TtxSessionService::class);

    $this->actingAs($admin);
    $session = $service->create($admin, $exercise, 'Flagship runtime');
    $team = $service->createTeam($admin, $session, 'Security / SOC');
    $service->updateTeamResponsibilities($admin, $session, $team->id, 'Validasi alert, investigasi autentikasi, dan tentukan cakupan insiden.');
    $service->assignParticipant($admin, $session, $participant, $team);
    foreach (collect($session->playbook_snapshot['structured_phases'])->pluck('key') as $phaseKey) {
        $service->assignResponsibility($admin, $session, $team->id, $phaseKey, 'primary');
    }

    expect($session->exercise_snapshot)->toMatchArray([
        'title' => TtxContentSeeder::FLAGSHIP_TITLE,
        'scenario' => $exercise->scenario,
        'objectives' => $exercise->objectives,
        'scope' => $exercise->scope,
        'capability_codes' => ['EX-1', 'EX-2', 'EX-3', 'EX-4', 'EX-5', 'EX-6'],
    ])->and($session->injects()->count())->toBe(5)
        ->and($session->response_contract_version)->toBe(2)
        ->and($session->playbook_snapshot['structured_phases'])->toHaveCount(9)
        ->and($session->injects()->pluck('order')->all())->toBe([1, 2, 3, 4, 5])
        ->and($session->injects()->get()->every(
            fn ($inject) => filled($inject->inject_snapshot['title'] ?? null)
                && filled($inject->inject_snapshot['description'] ?? null)
        ))->toBeTrue();

    $service->markReady($admin, $session);
    $service->start($admin, $session->fresh());

    $participantPayload = $service->readModel($participant, $session->fresh());
    $facilitatorPayload = $service->readModel($admin, $session->fresh());
    $participantJson = json_encode($participantPayload);

    expect($participantPayload['injects'])->toHaveCount(1)
        ->and($participantPayload['injects'][0]['order'])->toBe(1)
        ->and($participantPayload['injects'][0]['snapshot']['situation'])->toContain('Pukul 09.10')
        ->and($participantPayload['injects'][0]['snapshot']['known_facts'])->toHaveCount(4)
        ->and($participantPayload['injects'][0]['snapshot']['discussion_prompt'])->toContain('30 menit berikutnya')
        ->and($participantJson)->toContain('Suspicious Account Activity')
        ->and($participantJson)->not->toContain(
            'Confirmed Credential Compromise',
            'Lateral Business Impact',
            'External and Internal Communication Pressure',
            'Recovery and Lessons',
        )
        ->and($facilitatorPayload['injects'])->toHaveCount(5);
});

test('flagship runtime creation rejects wrong roles and cross-tenant administrators', function () {
    [, , $exercise] = seedFlagshipFixture();
    $learner = User::factory()->create(['tenant_id' => $exercise->tenant_id]);
    $otherTenantAdmin = User::factory()->tenantAdmin()->create([
        'tenant_id' => Tenant::factory()->create()->id,
    ]);
    $service = app(TtxSessionService::class);

    expect(fn () => $service->create($learner, $exercise, 'Wrong role'))
        ->toThrow(HttpException::class)
        ->and(fn () => $service->create($otherTenantAdmin, $exercise, 'Cross tenant'))
        ->toThrow(HttpException::class);
});
