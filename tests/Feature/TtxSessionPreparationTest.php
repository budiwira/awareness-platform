<?php

use App\Enums\TtxSessionRole;
use App\Enums\TtxSessionStatus;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxSession;
use App\Models\User;
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

    $allowed = ['id', 'title', 'status', 'scheduled_at', 'facilitator_name', 'participant_count'];

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
        'session', 'exercise_title', 'inject_count', 'facilitator',
        'facilitator_count', 'participants', 'readiness', 'can_open_console',
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
            expect($encoded)->not->toContain('Skenario rahasia');
            expect($encoded)->not->toContain('Deskripsi inject rahasia');
        });
});

test('can_open_console is false for a tenant admin who is not the facilitator', function () {
    [$tenant, $admin, , $session] = ttxPreparationFixture();

    $this->actingAs($admin)
        ->get(route('tenant.ttx.sessions.prepare', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can_open_console', false));
});

test('can_open_console matches the facilitate policy for an admin who is also facilitator', function () {
    [$tenant, $admin, , $session] = ttxPreparationFixture();

    // Admin is legitimately a facilitator of the session.
    app(TtxSessionService::class)->assignParticipant($admin, $session, $admin, TtxSessionRole::Facilitator);

    $expected = Gate::forUser($admin->fresh())->allows('facilitate', $session->fresh());
    expect($expected)->toBeTrue();

    $this->actingAs($admin)
        ->get(route('tenant.ttx.sessions.prepare', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can_open_console', $expected));
});
