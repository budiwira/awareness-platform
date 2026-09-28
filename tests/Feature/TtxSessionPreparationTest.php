<?php

use App\Enums\TtxSessionStatus;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxSession;
use App\Models\User;
use App\Services\TenantEntitlement;
use App\Services\TtxSessionService;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Build a Tenant Admin + exercise + session fixture inside one tenant.
 *
 * @return array{0: Tenant, 1: User, 2: TtxExercise, 3: TtxSession}
 */
function ttxPreparationFixture(): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $exercise = TtxExercise::create([
        'tenant_id' => $tenant->id,
        'title' => 'Krisis Komunikasi',
        'scenario' => 'Skenario rahasia',
        'objectives' => 'Uji koordinasi',
        'scope' => 'Lintas divisi',
    ]);
    $exercise = attachTtxTestPlaybook($exercise);
    TtxInject::create([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'order' => 1,
        'title' => 'Inject pertama',
        'description' => 'Deskripsi inject rahasia',
    ]);

    $session = app(TtxSessionService::class)->create($admin, $exercise, 'Sesi Latihan');

    return [$tenant, $admin, $exercise, $session];
}

test('active tenant admin can access the sessions index', function () {
    [$tenant, $admin, , $session] = ttxPreparationFixture();

    $this->actingAs($admin)
        ->get(route('tenant.ttx.sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Ttx/Sessions/Index')
            ->has('sessions', 1)
            ->where('sessions.0.id', $session->id)
            ->where('sessions.0.title', 'Sesi Latihan')
        );
});

test('learner cannot access the sessions index', function () {
    [$tenant, $admin] = ttxPreparationFixture();
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($learner)
        ->get(route('tenant.ttx.sessions.index'))
        ->assertForbidden();
});

test('inactive tenant admin cannot access the sessions index', function () {
    [$tenant, $admin] = ttxPreparationFixture();
    $admin->update(['is_active' => false]);

    $this->actingAs($admin->fresh())
        ->get(route('tenant.ttx.sessions.index'))
        ->assertForbidden();
});

test('sessions index returns only current tenant sessions', function () {
    [$tenant, $admin, , $ownSession] = ttxPreparationFixture();

    // Cross-tenant session that must never appear.
    $otherTenant = Tenant::factory()->create();
    $otherAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $otherTenant->id]);
    $otherExercise = TtxExercise::create([
        'tenant_id' => $otherTenant->id,
        'title' => 'Exercise lain',
        'scenario' => 'x',
        'objectives' => 'y',
        'scope' => 'z',
    ]);
    $otherExercise = attachTtxTestPlaybook($otherExercise);
    TtxInject::create([
        'tenant_id' => $otherTenant->id,
        'exercise_id' => $otherExercise->id,
        'order' => 1,
        'title' => 'Inject lain',
        'description' => 'd',
    ]);
    $otherSession = app(TtxSessionService::class)->create($otherAdmin, $otherExercise, 'Sesi Tenant Lain');

    $this->actingAs($admin)
        ->get(route('tenant.ttx.sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions', 1)
            ->where('sessions.0.id', $ownSession->id)
            ->where('sessions', fn ($sessions) => collect($sessions)->pluck('id')->doesntContain($otherSession->id))
        );
});

test('sessions index excludes sessions owned by another tenant admin', function () {
    [$tenant, $admin, $exercise, $ownSession] = ttxPreparationFixture();
    $otherAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $otherSession = app(TtxSessionService::class)->create($otherAdmin, $exercise, 'Session Admin Lain');

    $this->actingAs($admin)
        ->get(route('tenant.ttx.sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions', 1)
            ->where('sessions.0.id', $ownSession->id)
            ->where('sessions', fn ($sessions) => collect($sessions)->pluck('id')->doesntContain($otherSession->id))
        );
});

test('sessions index is ordered newest created first', function () {
    // Isolated tenant so only our two sessions participate.
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $exercise = TtxExercise::create([
        'tenant_id' => $tenant->id,
        'title' => 'Krisis Komunikasi',
        'scenario' => 'Skenario rahasia',
        'objectives' => 'Uji koordinasi',
        'scope' => 'Lintas divisi',
    ]);
    $exercise = attachTtxTestPlaybook($exercise);

    $older = TtxSession::forceCreate([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'title' => 'Sesi Lama',
        'created_by' => $admin->id,
        'status' => TtxSessionStatus::Draft,
        'exercise_snapshot' => ['title' => $exercise->title],
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);
    $newer = TtxSession::forceCreate([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'title' => 'Sesi Baru',
        'created_by' => $admin->id,
        'status' => TtxSessionStatus::Draft,
        'exercise_snapshot' => ['title' => $exercise->title],
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('tenant.ttx.sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.id', $newer->id)
            ->where('sessions.1.id', $older->id)
        );
});

test('sessions index exposes only the safe list projection', function () {
    [$tenant, $admin, , $session] = ttxPreparationFixture();

    $allowed = [
        'id', 'title', 'scenario', 'status', 'scheduled_at', 'facilitator_name',
        'participant_count', 'team_count', 'action_label', 'action_url',
    ];

    $this->actingAs($admin)
        ->get(route('tenant.ttx.sessions.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($allowed) {
            $sessions = $page->toArray()['props']['sessions'] ?? [];
            expect($sessions)->toHaveCount(1);

            foreach ($sessions as $row) {
                expect(array_keys($row))->toEqualCanonicalizing($allowed);
                expect($row)->not->toHaveKey('exercise_snapshot');
                expect($row)->not->toHaveKey('inject_snapshot');
                expect($row)->not->toHaveKey('injects');
                expect($row)->not->toHaveKey('responses');
            }
        });
});

test('session creation redirects to the Inertia preparation page rather than runtime JSON', function () {
    [$tenant, $admin, $exercise] = ttxPreparationFixture();
    $this->mock(TenantEntitlement::class)
        ->shouldReceive('hasFeature')
        ->once()
        ->andReturnTrue();
    app(TenantEntitlement::class)->shouldReceive('getEntitledFeatures')->andReturn(['ttx']);
    app(TenantEntitlement::class)->shouldReceive('getEntitledModuleIds')->andReturn([]);

    $response = $this->actingAs($admin)->post(route('tenant.ttx.sessions.store', $exercise), [
        'playbook_id' => $exercise->playbook_id,
        'title' => 'Session dari UI',
        'scheduled_at' => '2026-10-01 09:00:00',
    ]);

    $created = TtxSession::query()->where('title', 'Session dari UI')->sole();
    $response->assertRedirect(route('tenant.ttx.sessions.prepare', $created));
    expect($response->headers->get('Location'))->not->toBe(route('tenant.ttx.sessions.show', $created));
});

test('sessions index CTA routes are Inertia pages and never the runtime JSON endpoint', function () {
    [$tenant, $admin, $exercise, $draft] = ttxPreparationFixture();
    $service = app(TtxSessionService::class);
    $expected = [
        'draft' => ['Siapkan Session', 'tenant.ttx.sessions.prepare'],
        'ready' => ['Buka Facilitator Console', 'tenant.ttx.sessions.console'],
        'in_progress' => ['Lanjutkan Exercise', 'tenant.ttx.sessions.console'],
        'debrief' => ['Buka Debrief', 'tenant.ttx.sessions.debrief'],
        'completed' => ['Lihat Hasil', 'tenant.ttx.sessions.result'],
    ];

    foreach ($expected as $status => [, $routeName]) {
        $session = $status === 'draft'
            ? $draft
            : $service->create($admin, $exercise, "Session {$status}");
        $session->forceFill(['status' => $status])->save();
    }

    $rows = collect($service->index($admin))->keyBy('status');
    foreach ($expected as $status => [$label, $routeName]) {
        $row = $rows->get($status);
        expect($row['action_label'])->toBe($label)
            ->and($row['action_url'])->toBe(route($routeName, $row['id']))
            ->and($row['action_url'])->not->toBe(route('tenant.ttx.sessions.show', $row['id']));

        $this->actingAs($admin)->get($row['action_url'])->assertOk()->assertInertia();
    }

    $this->actingAs($admin)
        ->getJson(route('tenant.ttx.sessions.show', $draft))
        ->assertOk()
        ->assertJsonPath('id', $draft->id)
        ->assertHeader('content-type', 'application/json');
});

test('tenant admin can open preparation for own session without participant membership', function () {
    [$tenant, $admin, , $session] = ttxPreparationFixture();

    // Sanity: admin is NOT a participant of this session.
    expect($session->participants()->where('user_id', $admin->id)->exists())->toBeFalse();

    $this->actingAs($admin)
        ->get(route('tenant.ttx.sessions.prepare', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Ttx/Sessions/Prepare')
            ->where('session.id', $session->id)
            ->where('session.status', 'draft')
            ->where('exercise_title', 'Krisis Komunikasi')
            ->where('inject_count', 1)
        );
});

test('participant and learner cannot open preparation', function () {
    [$tenant, $admin, $exercise] = ttxPreparationFixture();

    // Learner (role User) is blocked by the tenant dashboard middleware.
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($learner)
        ->get(route('tenant.ttx.sessions.prepare', $exercise->sessions()->first()))
        ->assertForbidden();

    // A Tenant Admin of another tenant is blocked by the prepare policy.
    $otherTenant = Tenant::factory()->create();
    $otherAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $otherTenant->id]);
    $session = $exercise->sessions()->first();

    $this->actingAs($otherAdmin)
        ->get(route('tenant.ttx.sessions.prepare', $session))
        ->assertForbidden();
});

test('preparation payload is a minimal allowlisted shape without snapshots or future injects', function () {
    [$tenant, $admin, , $session] = ttxPreparationFixture();

    $allowedTop = [
        'session', 'exercise_title', 'exercise_context', 'capabilities', 'playbook', 'inject_count', 'facilitator',
        'teams', 'participants', 'readiness', 'can_open_console',
    ];

    $this->actingAs($admin)
        ->get(route('tenant.ttx.sessions.prepare', $session))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($allowedTop) {
            $props = $page->toArray()['props'] ?? [];

            // All allowlisted props must be present (shared Inertia props may be merged in).
            foreach ($allowedTop as $key) {
                expect($props)->toHaveKey($key);
            }
            expect($props)->not->toHaveKey('exercise_snapshot');
            expect($props)->not->toHaveKey('inject_snapshot');
            expect($props)->not->toHaveKey('injects');
            expect($props)->not->toHaveKey('responses');

            // Session summary is a small scalar subset only.
            expect(array_keys($props['session']))->toEqualCanonicalizing(['id', 'title', 'status', 'scheduled_at']);

            $encoded = json_encode($props);
            expect($props['exercise_context']['scenario'])->toBe('Skenario rahasia');
            expect(array_keys($props['exercise_context']))->toEqualCanonicalizing(['title', 'scenario', 'objectives', 'scope', 'capability_codes']);
            expect($encoded)->not->toContain('Deskripsi inject rahasia');
        });
});

test('creator tenant admin can open the facilitator console automatically', function () {
    [$tenant, $admin, , $session] = ttxPreparationFixture();

    $this->actingAs($admin)
        ->get(route('tenant.ttx.sessions.prepare', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can_open_console', true));
});

test('can_open_console matches the creator facilitator policy', function () {
    [$tenant, $admin, , $session] = ttxPreparationFixture();

    $expected = Gate::forUser($admin->fresh())->allows('facilitate', $session->fresh());
    expect($expected)->toBeTrue();

    $this->actingAs($admin)
        ->get(route('tenant.ttx.sessions.prepare', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can_open_console', $expected));
});
