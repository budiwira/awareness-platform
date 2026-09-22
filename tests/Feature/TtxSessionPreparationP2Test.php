<?php

use App\Enums\TtxSessionRole;
use App\Enums\TtxSessionStatus;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxSession;
use App\Models\User;
use App\Services\TtxSessionService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

function preparationP2Fixture(): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $exercise = TtxExercise::create(['tenant_id' => $tenant->id, 'title' => 'Latihan', 'scenario' => 'SECRET SCENARIO']);
    TtxInject::create(['tenant_id' => $tenant->id, 'exercise_id' => $exercise->id, 'order' => 1, 'title' => 'SECRET INJECT']);
    $service = app(TtxSessionService::class);
    $session = $service->create($admin, $exercise, 'Persiapan');

    return [$tenant, $admin, $session, $service];
}

test('p2 assigns allowed roles and returns authoritative safe preparation', function (string $role) {
    [$tenant, $admin, $session] = preparationP2Fixture();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($admin)->postJson(route('tenant.ttx.sessions.participants.store', $session), [
        'user_id' => $user->id, 'role' => $role, 'tenant_id' => Tenant::factory()->create()->id,
    ])->assertOk()->assertJsonPath('participants.0.id', $user->id)
        ->assertJsonPath('participants.0.role', $role)->assertJsonPath('can_open_console', false);
    expect($session->participants()->sole()->tenant_id)->toBe($tenant->id);
})->with(['facilitator', 'security', 'it_operations', 'people_hr', 'communications', 'management']);

test('p2 rejects inactive foreign duplicate and invalid assignments', function () {
    [$tenant, $admin, $session] = preparationP2Fixture();
    $url = route('tenant.ttx.sessions.participants.store', $session);
    $inactive = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);
    $foreign = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $this->actingAs($admin)->postJson($url, ['user_id' => $inactive->id, 'role' => 'security'])->assertForbidden();
    $this->postJson($url, ['user_id' => $foreign->id, 'role' => 'security'])->assertForbidden();
    $this->postJson($url, ['user_id' => $admin->id, 'role' => 'owner'])->assertUnprocessable();
    $this->postJson($url, ['user_id' => $admin->id, 'role' => 'facilitator'])->assertOk()->assertJsonPath('can_open_console', true);
    $this->postJson($url, ['user_id' => $admin->id, 'role' => 'security'])->assertConflict();
    $other = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->postJson($url, ['user_id' => $other->id, 'role' => 'facilitator'])->assertConflict();
    expect($session->participants()->count())->toBe(1);
});

test('p2 learner and inactive admin cannot mutate either endpoint', function (string $actorType) {
    [$tenant, $admin, $session, $service] = preparationP2Fixture();
    $assignment = $service->assignParticipant($admin, $session, $admin, TtxSessionRole::Facilitator);
    $actor = $actorType === 'learner' ? User::factory()->create(['tenant_id' => $tenant->id]) : $admin;
    if ($actorType === 'inactive') {
        $actor->update(['is_active' => false]);
    }
    $this->actingAs($actor)->postJson(route('tenant.ttx.sessions.participants.store', $session), ['user_id' => $admin->id, 'role' => 'security'])->assertForbidden();
    $this->deleteJson(route('tenant.ttx.sessions.participants.destroy', [$session, $assignment->id]))->assertForbidden();
    expect($assignment->fresh())->not->toBeNull();
})->with(['learner', 'inactive']);

test('p2 draft removal permits reassignment and audits only identifiers', function (string $role) {
    [$tenant, $admin, $session, $service] = preparationP2Fixture();
    $assignment = $service->assignParticipant($admin, $session, $admin, TtxSessionRole::from($role));
    $this->actingAs($admin)->deleteJson(route('tenant.ttx.sessions.participants.destroy', [$session, $assignment->id]))
        ->assertOk()->assertJsonCount(0, 'participants')->assertJsonPath('can_open_console', false);
    expect($assignment->fresh())->toBeNull();
    $audit = AuditLog::where('action', 'ttx.session_participant_removed')->where('subject_id', (string) $assignment->id)->sole();
    expect($audit->properties)->toEqual(['session_id' => $session->id, 'user_id' => $admin->id, 'role' => $role]);
    expect($audit->actor_user_id)->toBe($admin->id);
    $replacement = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->postJson(route('tenant.ttx.sessions.participants.store', $session), ['user_id' => $replacement->id, 'role' => 'facilitator'])->assertOk();
})->with(['facilitator', 'security']);

test('p2 ready preserves facilitator and last participant but allows safe removal and addition', function () {
    [$tenant, $admin, $session, $service] = preparationP2Fixture();
    $facilitator = $service->assignParticipant($admin, $session, $admin, TtxSessionRole::Facilitator);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $participant = $service->assignParticipant($admin, $session, $user, TtxSessionRole::Security);
    $service->markReady($admin, $session);
    $this->actingAs($admin)->deleteJson(route('tenant.ttx.sessions.participants.destroy', [$session, $facilitator->id]))->assertConflict();
    $this->deleteJson(route('tenant.ttx.sessions.participants.destroy', [$session, $participant->id]))->assertConflict();
    $other = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->postJson(route('tenant.ttx.sessions.participants.store', $session), ['user_id' => $other->id, 'role' => 'facilitator'])->assertConflict();
    $this->postJson(route('tenant.ttx.sessions.participants.store', $session), ['user_id' => $other->id, 'role' => 'management'])->assertOk();
    $this->deleteJson(route('tenant.ttx.sessions.participants.destroy', [$session, $participant->id]))->assertOk()->assertJsonPath('session.status', 'ready')->assertJsonPath('readiness.ready', true);
    expect($session->fresh()->status)->toBe(TtxSessionStatus::Ready);
});

test('p2 immutable lifecycle rejects assignment and removal including stale service models', function (string $status) {
    [$tenant, $admin, $session, $service] = preparationP2Fixture();
    $assignment = $service->assignParticipant($admin, $session, $admin, TtxSessionRole::Facilitator);
    $session->fresh()->update(['status' => $status]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($admin)->postJson(route('tenant.ttx.sessions.participants.store', $session), ['user_id' => $user->id, 'role' => 'security'])->assertForbidden();
    $this->deleteJson(route('tenant.ttx.sessions.participants.destroy', [$session, $assignment->id]))->assertConflict();
    expect(fn () => $service->assignParticipant($admin, $session, $user, TtxSessionRole::Security))->toThrow(HttpException::class);
    expect(fn () => $service->removeParticipant($admin, $session, $assignment->id))->toThrow(HttpException::class);
    expect($assignment->fresh())->not->toBeNull();
})->with(['in_progress', 'debrief', 'completed']);

test('p2 removal is scoped to URL session and tenant', function (bool $foreignTenant) {
    [$tenant, $admin, $session, $service] = preparationP2Fixture();
    if ($foreignTenant) {
        [, $otherAdmin, $otherSession] = preparationP2Fixture();
    } else {
        $otherAdmin = $admin;
        $otherSession = $service->create($admin, $session->exercise, 'Autre');
    }
    $assignment = $service->assignParticipant($otherAdmin, $otherSession, $otherAdmin, TtxSessionRole::Facilitator);
    $this->actingAs($admin)->deleteJson(route('tenant.ttx.sessions.participants.destroy', [$session, $assignment->id]), ['tenant_id' => $otherSession->tenant_id])->assertNotFound();
    expect($assignment->fresh())->not->toBeNull();
    expect(AuditLog::where('action', 'ttx.session_participant_removed')->exists())->toBeFalse();
})->with([false, true]);

test('p2 preparation exposes only active unassigned same tenant users and allowed roles', function () {
    [$tenant, $admin, $session, $service] = preparationP2Fixture();
    $service->assignParticipant($admin, $session, $admin, TtxSessionRole::Facilitator);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);
    User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $payload = $service->preparationReadModel($admin, $session->fresh());
    expect($payload['assignable_users'])->toBe([['id' => $user->id, 'name' => $user->name]]);
    expect(array_column($payload['role_options'], 'value'))->toBe(array_column(TtxSessionRole::cases(), 'value'));
    expect(array_keys($payload))->toEqualCanonicalizing(['session', 'exercise_title', 'inject_count', 'facilitator', 'facilitator_count', 'participants', 'readiness', 'permissions', 'can_assign', 'can_assign_facilitator', 'assignable_users', 'role_options', 'can_open_console']);
    expect(json_encode($payload))->not->toContain('SECRET');
    expect($payload['readiness']['has_participants'])->toBeFalse();
});

test('p2 ready removal checks all readiness prerequisites', function (string $invalid) {
    [$tenant, $admin, $session, $service] = preparationP2Fixture();
    $service->assignParticipant($admin, $session, $admin, TtxSessionRole::Facilitator);
    $participants = User::factory()->count(2)->create(['tenant_id' => $tenant->id]);
    foreach ($participants as $user) {
        $assignment = $service->assignParticipant($admin, $session, $user, TtxSessionRole::Security);
    }
    $service->markReady($admin, $session);
    match ($invalid) {
        'snapshot' => $session->update(['exercise_snapshot' => []]),
        'started' => $session->update(['started_at' => now()]),
        'injects' => $session->injects()->delete(),
        'pending' => $session->injects()->update(['status' => 'active']),
        'facilitator' => $session->participants()->where('session_role', 'facilitator')->delete(),
    };
    $this->actingAs($admin)->deleteJson(route('tenant.ttx.sessions.participants.destroy', [$session, $assignment->id]))->assertConflict();
    expect($assignment->fresh())->not->toBeNull();
    expect($session->fresh()->status)->toBe(TtxSessionStatus::Ready);
})->with(['snapshot', 'started', 'injects', 'pending', 'facilitator']);

test('p2 roster operations retain tenant isolation under PostgreSQL runtime RLS', function () {
    $original = DB::getDefaultConnection();
    DB::setDefaultConnection('pgsql_owner');
    [$tenant, $admin, $session, $service] = preparationP2Fixture();
    [$foreignTenant, $foreignAdmin, $foreignSession] = preparationP2Fixture();
    $foreignAssignment = $service->assignParticipant($foreignAdmin, $foreignSession, $foreignAdmin, TtxSessionRole::Facilitator);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    config(['database.connections.p2_runtime' => array_merge(config('database.connections.pgsql'), [
        'username' => env('DB_APP_USERNAME'),
        'password' => env('DB_APP_PASSWORD'),
    ])]);
    DB::setDefaultConnection('p2_runtime');
    $this->actingAs($admin);
    try {
        DB::select("SELECT set_config('app.tenant_id', ?, false)", [$tenant->id]);
        $this->postJson(route('tenant.ttx.sessions.participants.store', $session), ['user_id' => $user->id, 'role' => 'security'])->assertOk();
        $this->postJson(route('tenant.ttx.sessions.participants.store', $session), ['user_id' => $foreignAdmin->id, 'role' => 'security'])->assertNotFound();
        $this->deleteJson(route('tenant.ttx.sessions.participants.destroy', [$session, $foreignAssignment->id]))->assertNotFound();
        DB::select("SELECT set_config('app.tenant_id', ?, false)", [$tenant->id]);
        $runtimeSession = TtxSession::findOrFail($session->id);
        $payload = $service->preparationReadModel($admin, $runtimeSession);
        expect(array_column($payload['assignable_users'], 'id'))->not->toContain($foreignAdmin->id, $user->id);
        $assignment = $runtimeSession->participants()->sole();
        $this->deleteJson(route('tenant.ttx.sessions.participants.destroy', [$session, $assignment->id]))->assertOk();
        expect($foreignAssignment->fresh())->not->toBeNull();
    } finally {
        DB::setDefaultConnection('pgsql_owner');
        AuditLog::whereIn('tenant_id', [$tenant->id, $foreignTenant->id])->delete();
        foreach ([$session, $foreignSession] as $cleanupSession) {
            $cleanupSession->injects()->delete();
            $cleanupSession->participants()->delete();
        }
        $session->delete();
        $foreignSession->delete();
        TtxInject::whereIn('tenant_id', [$tenant->id, $foreignTenant->id])->delete();
        TtxExercise::whereIn('tenant_id', [$tenant->id, $foreignTenant->id])->delete();
        User::whereIn('tenant_id', [$tenant->id, $foreignTenant->id])->forceDelete();
        Tenant::whereIn('id', [$tenant->id, $foreignTenant->id])->delete();
        DB::purge('p2_runtime');
        DB::setDefaultConnection($original);
    }
});
