<?php

use App\Enums\TtxSessionRole;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\User;
use App\Services\TtxSessionService;
use Database\Seeders\TtxContentSeeder;
use Symfony\Component\HttpKernel\Exception\HttpException;

function seedFlagshipFixture(): array
{
    $tenant = Tenant::factory()->create([
        'name' => 'Arunika Tenant',
        'slug' => 'acme',
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

    expect($exercise->title)->toBe('Credential Compromise & Coordinated Incident Response')
        ->and($exercise->tenant_id)->toBe($tenant->id)
        ->and($exercise->phase)->toBe('planning')
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

    expect(collect(TtxContentSeeder::FLAGSHIP_OBJECTIVE_MAP)->flatten()->unique()->sort()->values()->all())
        ->toBe(['EX-1', 'EX-2', 'EX-3', 'EX-4', 'EX-5', 'EX-6']);
});

test('flagship seeding is idempotent and preserves its original tenant association', function () {
    [$tenant, , $exercise] = seedFlagshipFixture();
    $otherTenant = Tenant::factory()->create(['slug' => 'beta']);

    (new TtxContentSeeder)->run();

    expect(TtxExercise::query()->where('title', TtxContentSeeder::FLAGSHIP_TITLE)->count())->toBe(1)
        ->and(TtxExercise::query()->whereKey($exercise->id)->value('tenant_id'))->toBe($tenant->id)
        ->and(TtxExercise::query()->where('tenant_id', $otherTenant->id)->where('title', TtxContentSeeder::FLAGSHIP_TITLE)->exists())->toBeFalse()
        ->and($exercise->fresh()->injects()->count())->toBe(5)
        ->and($exercise->fresh()->injects()->pluck('order')->all())->toBe([1, 2, 3, 4, 5]);
});

test('flagship exercise creates complete runtime snapshots and keeps future injects confidential', function () {
    [$tenant, $admin, $exercise] = seedFlagshipFixture();
    $facilitator = User::factory()->create(['tenant_id' => $tenant->id]);
    $participant = User::factory()->create(['tenant_id' => $tenant->id]);
    $service = app(TtxSessionService::class);

    $this->actingAs($admin);
    $session = $service->create($admin, $exercise, 'Flagship runtime');
    $service->assignParticipant($admin, $session, $facilitator, TtxSessionRole::Facilitator);
    $service->assignParticipant($admin, $session, $participant, TtxSessionRole::Security);

    expect($session->exercise_snapshot)->toMatchArray([
        'title' => TtxContentSeeder::FLAGSHIP_TITLE,
        'scenario' => $exercise->scenario,
        'objectives' => $exercise->objectives,
        'scope' => $exercise->scope,
    ])->and($session->injects()->count())->toBe(5)
        ->and($session->injects()->pluck('order')->all())->toBe([1, 2, 3, 4, 5])
        ->and($session->injects()->get()->every(
            fn ($inject) => filled($inject->inject_snapshot['title'] ?? null)
                && filled($inject->inject_snapshot['description'] ?? null)
        ))->toBeTrue();

    $service->markReady($admin, $session);
    $service->start($facilitator, $session->fresh());

    $participantPayload = $service->readModel($participant, $session->fresh());
    $facilitatorPayload = $service->readModel($facilitator, $session->fresh());
    $participantJson = json_encode($participantPayload);

    expect($participantPayload['injects'])->toHaveCount(1)
        ->and($participantPayload['injects'][0]['order'])->toBe(1)
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
