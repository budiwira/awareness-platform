<?php

use App\Enums\TtxSessionInjectStatus;
use App\Enums\TtxSessionRole;
use App\Enums\TtxSessionStatus;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxSession;
use App\Models\TtxSessionInject;
use App\Models\TtxSessionParticipant;
use App\Models\TtxSessionResponse;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function participantWorkspaceFixture(): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $facilitator = User::factory()->create(['tenant_id' => $tenant->id]);
    $participant = User::factory()->create(['tenant_id' => $tenant->id]);

    $exercise = TtxExercise::create([
        'tenant_id' => $tenant->id,
        'title' => 'Workspace exercise',
        'scenario' => 'Workspace scenario',
    ]);

    $session = TtxSession::forceCreate([
        'tenant_id' => $tenant->id,
        'exercise_id' => $exercise->id,
        'title' => 'Workspace session',
        'created_by' => $admin->id,
        'status' => TtxSessionStatus::InProgress,
        'started_at' => now(),
        'exercise_snapshot' => ['title' => $exercise->title],
    ]);

    TtxSessionParticipant::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'user_id' => $facilitator->id,
        'session_role' => TtxSessionRole::Facilitator,
    ]);

    TtxSessionParticipant::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'user_id' => $participant->id,
        'session_role' => TtxSessionRole::Security,
    ]);

    $sessionInjects = collect([
        [TtxSessionInjectStatus::Locked, 'Situasi lama', 'Fakta lama'],
        [TtxSessionInjectStatus::Active, 'Situasi aktif', 'Fakta aktif'],
        [TtxSessionInjectStatus::Pending, 'Situasi rahasia', 'Fakta rahasia'],
    ])->map(function (array $definition, int $index) use ($tenant, $exercise, $session, $facilitator) {
        [$status, $situation, $knownFact] = $definition;
        $order = $index + 1;
        $inject = TtxInject::create([
            'tenant_id' => $tenant->id,
            'exercise_id' => $exercise->id,
            'order' => $order,
            'title' => "Inject {$order}",
            'description' => $situation,
        ]);

        return TtxSessionInject::forceCreate([
            'tenant_id' => $tenant->id,
            'session_id' => $session->id,
            'inject_id' => $inject->id,
            'order' => $order,
            'status' => $status,
            'inject_snapshot' => [
                'title' => $inject->title,
                'description' => $inject->description,
                'situation' => $situation,
                'known_facts' => [$knownFact],
                'discussion_prompt' => "Diskusikan inject {$order}",
                'facilitator_notes' => "Catatan fasilitator {$order}",
                'evaluation' => ['score' => 99],
            ],
            'released_at' => $status === TtxSessionInjectStatus::Pending ? null : now(),
            'released_by' => $status === TtxSessionInjectStatus::Pending ? null : $facilitator->id,
            'locked_at' => $status === TtxSessionInjectStatus::Locked ? now() : null,
        ]);
    });

    return [$tenant, $admin, $facilitator, $participant, $session, $sessionInjects];
}

test('assigned active non-facilitator participant can open workspace', function () {
    [, , , $participant, $session] = participantWorkspaceFixture();

    $this->actingAs($participant)
        ->get(route('tenant.ttx.sessions.workspace', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Ttx/Sessions/ParticipantWorkspace')
            ->where('sessionId', $session->id));
});

test('participant workspace rejects facilitator and users outside its security boundary', function (string $actor) {
    [$tenant, , $facilitator, $participant, $session] = participantWorkspaceFixture();

    $user = match ($actor) {
        'facilitator' => $facilitator,
        'unassigned' => User::factory()->create(['tenant_id' => $tenant->id]),
        'cross-tenant' => User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]),
        'tenant-admin' => User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]),
        'super-admin' => User::factory()->superAdmin()->create(),
        'inactive' => tap($participant)->update(['is_active' => false]),
    };

    $this->actingAs($user->fresh())
        ->get(route('tenant.ttx.sessions.workspace', $session))
        ->assertForbidden();
})->with([
    'facilitator',
    'unassigned',
    'cross-tenant',
    'tenant-admin',
    'super-admin',
    'inactive',
]);

test('participant runtime exposes only released allow-listed inject content', function () {
    [, , , $participant, $session, $injects] = participantWorkspaceFixture();

    $response = $this->actingAs($participant)
        ->getJson(route('tenant.ttx.sessions.show', $session))
        ->assertOk()
        ->assertJsonPath('actor_role', 'security')
        ->assertJsonPath('progress.total', 2)
        ->assertJsonCount(2, 'injects');

    $payload = $response->json();
    $json = json_encode($payload);

    expect(collect($payload['injects'])->pluck('id')->all())
        ->toBe([$injects[0]->id, $injects[1]->id])
        ->and($payload['injects'][1]['snapshot'])
        ->toHaveKeys(['title', 'description', 'situation', 'known_facts', 'discussion_prompt'])
        ->not->toHaveKeys(['facilitator_notes', 'evaluation'])
        ->and($json)
        ->not->toContain('Situasi rahasia')
        ->not->toContain('Fakta rahasia')
        ->not->toContain('Catatan fasilitator')
        ->not->toContain('evaluation');
});

test('facilitator runtime semantics remain unchanged', function () {
    [, , $facilitator, , $session] = participantWorkspaceFixture();

    $payload = $this->actingAs($facilitator)
        ->getJson(route('tenant.ttx.sessions.show', $session))
        ->assertOk()
        ->assertJsonPath('progress.total', 3)
        ->assertJsonCount(3, 'injects')
        ->json();

    expect($payload['injects'][1]['snapshot'])
        ->toHaveKeys(['facilitator_notes', 'evaluation']);
});

test('ready participant runtime has no released inject or active response workflow', function () {
    [, , , $participant, $session, $injects] = participantWorkspaceFixture();
    $session->update(['status' => TtxSessionStatus::Ready, 'started_at' => null]);
    $injects->each->update([
        'status' => TtxSessionInjectStatus::Pending,
        'released_at' => null,
        'released_by' => null,
        'locked_at' => null,
    ]);

    $this->actingAs($participant)
        ->getJson(route('tenant.ttx.sessions.show', $session))
        ->assertOk()
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('progress.current', null)
        ->assertJsonCount(0, 'injects');
});

test('in-progress participant runtime exposes active and previously locked injects', function () {
    [, , , $participant, $session, $injects] = participantWorkspaceFixture();

    TtxSessionResponse::forceCreate([
        'tenant_id' => $session->tenant_id,
        'session_id' => $session->id,
        'session_inject_id' => $injects[0]->id,
        'decision' => 'Keputusan terkunci',
        'revision' => 1,
        'submitted_by' => $participant->id,
        'submitted_at' => now(),
        'locked_at' => now(),
    ]);

    $payload = $this->actingAs($participant)
        ->getJson(route('tenant.ttx.sessions.show', $session))
        ->assertOk()
        ->json();

    expect(collect($payload['injects'])->firstWhere('id', $injects[0]->id)['response']['decision'])
        ->toBe('Keputusan terkunci')
        ->and(collect($payload['injects'])->firstWhere('id', $injects[1]->id)['status'])
        ->toBe('active');
});

test('debrief and completed participant runtimes are released read-only timelines', function (string $status) {
    [, , , $participant, $session, $injects] = participantWorkspaceFixture();
    $session->update(['status' => TtxSessionStatus::from($status)]);
    $injects[1]->update(['status' => TtxSessionInjectStatus::Locked, 'locked_at' => now()]);

    $payload = $this->actingAs($participant)
        ->getJson(route('tenant.ttx.sessions.show', $session))
        ->assertOk()
        ->assertJsonPath('status', $status)
        ->assertJsonCount(2, 'injects')
        ->json();

    expect(collect($payload['injects'])->every(fn (array $inject) => $inject['status'] === 'locked'))
        ->toBeTrue();
})->with([
    'debrief',
    'completed',
]);

test('participant team response preserves create update lock and optimistic concurrency rules', function () {
    [, , , $participant, $session, $injects] = participantWorkspaceFixture();

    $created = $this->actingAs($participant)->postJson(route('tenant.ttx.sessions.responses.store', $session), [
        'session_inject_id' => $injects[1]->id,
        'decision' => 'Isolasi layanan terdampak',
    ])->assertCreated();

    $responseId = $created->json('id');

    $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Isolasi dan eskalasi layanan',
    ])->assertOk()->assertJsonPath('revision', 2);

    $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 1,
        'decision' => 'Perubahan basi',
    ])->assertConflict();

    $injects[1]->update(['status' => TtxSessionInjectStatus::Locked, 'locked_at' => now()]);
    TtxSessionResponse::whereKey($responseId)->update(['locked_at' => now()]);

    $this->actingAs($participant)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $responseId]), [
        'expected_revision' => 2,
        'decision' => 'Tidak boleh berubah',
    ])->assertUnprocessable();
});

test('response mutation cannot bypass membership or tenant boundary', function (string $actor) {
    [$tenant, , , $participant, $session, $injects] = participantWorkspaceFixture();
    $response = TtxSessionResponse::forceCreate([
        'tenant_id' => $tenant->id,
        'session_id' => $session->id,
        'session_inject_id' => $injects[1]->id,
        'decision' => 'Respons resmi',
        'revision' => 1,
        'submitted_by' => $participant->id,
        'submitted_at' => now(),
    ]);
    $attacker = $actor === 'cross-tenant'
        ? User::factory()->create(['tenant_id' => Tenant::factory()->create()->id])
        : User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($attacker)->putJson(route('tenant.ttx.sessions.responses.update', [$session, $response]), [
        'expected_revision' => 1,
        'decision' => 'Respons penyerang',
    ])->assertForbidden();
})->with(['unassigned', 'cross-tenant']);
