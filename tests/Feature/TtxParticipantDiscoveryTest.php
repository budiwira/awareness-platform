<?php

use App\Enums\TtxSessionRole;
use App\Enums\TtxSessionStatus;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxSession;
use App\Models\TtxSessionParticipant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function ttxDiscoverySession(Tenant $tenant, User $creator, string $title, TtxSessionStatus $status): TtxSession
{
    $exercise = TtxExercise::create([
        'tenant_id' => $tenant->id,
        'title' => "Exercise {$title}",
        'scenario' => 'Scenario',
    ]);

    return TtxSession::forceCreate([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'title' => $title,
        'created_by' => $creator->id,
        'status' => $status,
        'exercise_snapshot' => [
            'title' => $exercise->title,
            'scenario' => 'Future scenario must not appear',
        ],
    ]);
}

function assignDiscoveryUser(TtxSession $session, User $user, TtxSessionRole $role): void
{
    TtxSessionParticipant::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'user_id' => $user->id,
        'session_role' => $role,
    ]);
}

test('assigned participant discovers own session with participant workspace action', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $participant = User::factory()->create(['tenant_id' => $tenant->id]);
    $session = ttxDiscoverySession($tenant, $admin, 'Sesi Milik Saya', TtxSessionStatus::InProgress);
    assignDiscoveryUser($session, $participant, TtxSessionRole::Security);

    $this->actingAs($participant)
        ->get(route('user.ttx.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/Ttx/Index')
            ->has('sessions', 1)
            ->where('sessions.0', [
                'id' => $session->id,
                'title' => 'Sesi Milik Saya',
                'status' => 'in_progress',
                'role' => 'security',
                'action_label' => 'Buka Workspace',
                'action_url' => route('tenant.ttx.sessions.workspace', $session),
            ]));
});

test('discovery excludes unassigned and cross-tenant sessions', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $otherAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $otherTenant->id]);
    $participant = User::factory()->create(['tenant_id' => $tenant->id]);
    $otherParticipant = User::factory()->create(['tenant_id' => $otherTenant->id]);

    ttxDiscoverySession($tenant, $admin, 'Tidak Ditugaskan', TtxSessionStatus::Ready);
    $crossTenant = ttxDiscoverySession($otherTenant, $otherAdmin, 'Tenant Lain', TtxSessionStatus::InProgress);
    assignDiscoveryUser($crossTenant, $otherParticipant, TtxSessionRole::Security);

    $this->actingAs($participant)
        ->get(route('user.ttx.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/Ttx/Index')
            ->has('sessions', 0));
});

test('facilitator discovery points to console and never participant workspace', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $facilitator = User::factory()->create(['tenant_id' => $tenant->id]);
    $session = ttxDiscoverySession($tenant, $admin, 'Sesi Fasilitator', TtxSessionStatus::Ready);
    assignDiscoveryUser($session, $facilitator, TtxSessionRole::Facilitator);

    $response = $this->actingAs($facilitator)
        ->get(route('user.ttx.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions', 1)
            ->where('sessions.0.action_label', 'Buka Konsol Fasilitator')
            ->where('sessions.0.action_url', route('tenant.ttx.sessions.console', $session)));

    expect($response->getContent())
        ->not->toContain(route('tenant.ttx.sessions.workspace', $session));
});

test('learner cannot enumerate another users assignments or sensitive runtime data', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $otherUser = User::factory()->create(['tenant_id' => $tenant->id]);
    $ownSession = ttxDiscoverySession($tenant, $admin, 'Sesi Pengguna Aktif', TtxSessionStatus::Debrief);
    $otherSession = ttxDiscoverySession($tenant, $admin, 'Sesi Pengguna Lain', TtxSessionStatus::InProgress);
    assignDiscoveryUser($ownSession, $learner, TtxSessionRole::Communications);
    assignDiscoveryUser($otherSession, $otherUser, TtxSessionRole::Management);

    $response = $this->actingAs($learner)
        ->get(route('user.ttx.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions', 1)
            ->where('sessions.0.title', 'Sesi Pengguna Aktif')
            ->where('sessions.0.action_label', 'Lihat Workspace')
            ->missing('sessions.0.tenant_id')
            ->missing('sessions.0.injects')
            ->missing('sessions.0.responses')
            ->missing('sessions.0.exercise_snapshot'));

    expect($response->getContent())
        ->not->toContain('Sesi Pengguna Lain')
        ->not->toContain('Future scenario must not appear');
});

test('inactive learner cannot open tabletop discovery', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('user.ttx.index'))
        ->assertForbidden();
});
