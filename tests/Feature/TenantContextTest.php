<?php

use App\Http\Middleware\SetTenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenant\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function tenantContextSnapshot(): array
{
    return (array) DB::selectOne("SELECT current_setting('app.tenant_id', true) AS tenant_id, current_setting('app.user_id', true) AS user_id, current_setting('app.role', true) AS role")
        + ['current_tenant' => app(CurrentTenant::class)->id()];
}

function expectTenantContextCleared(): void
{
    expect(tenantContextSnapshot())->toBe([
        'tenant_id' => '', 'user_id' => '', 'role' => '', 'current_tenant' => null,
    ]);
}

beforeEach(function () {
    Route::middleware('web')->get('/_test/tenant-context', fn () => response()->json(tenantContextSnapshot()));
});

test('tenant context is set from the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/_test/tenant-context')->assertOk()->assertExactJson([
        'tenant_id' => $user->tenant_id, 'user_id' => (string) $user->id,
        'role' => 'user', 'current_tenant' => $user->tenant_id,
    ]);

    expectTenantContextCleared();
});

test('tenant context ignores tenant_id injected in request', function () {
    $user = User::factory()->create();
    $evilTenant = Tenant::factory()->create();

    $this->actingAs($user)
        ->get('/_test/tenant-context?tenant_id='.$evilTenant->id)
        ->assertOk()->assertJsonPath('tenant_id', $user->tenant_id)
        ->assertJsonPath('current_tenant', $user->tenant_id);

    expectTenantContextCleared();
});

test('session-derived bootstrap cannot expose another tenant', function () {
    $sessionUser = User::factory()->create();
    $otherTenantUser = User::factory()->create();
    $sessionKey = Auth::guard('web')->getName();

    $this->withSession([$sessionKey => $sessionUser->id])
        ->get('/_test/tenant-context?user_id='.$otherTenantUser->id.'&tenant_id='.$otherTenantUser->tenant_id)
        ->assertOk()
        ->assertExactJson([
            'tenant_id' => $sessionUser->tenant_id,
            'user_id' => (string) $sessionUser->id,
            'role' => 'user',
            'current_tenant' => $sessionUser->tenant_id,
        ]);

    expectTenantContextCleared();
});

test('super admin has no tenant context', function () {
    $super = User::factory()->superAdmin()->create();
    app(CurrentTenant::class)->set(Tenant::factory()->create()->id);

    $this->actingAs($super)->get('/_test/tenant-context')->assertOk()->assertExactJson([
        'tenant_id' => '', 'user_id' => (string) $super->id,
        'role' => 'super_admin', 'current_tenant' => null,
    ]);
    expectTenantContextCleared();
    $this->actingAs($super)->get(route('platform.dashboard'))->assertOk();

    expectTenantContextCleared();
});

test('TenantContext clears stale database and memory context for guest requests', function () {
    $user = User::factory()->create();
    DB::statement("SELECT set_config('app.tenant_id', ?, false), set_config('app.user_id', ?, false), set_config('app.role', 'super_admin', false)", [$user->tenant_id, (string) $user->id]);
    app(CurrentTenant::class)->set($user->tenant_id);

    $this->get('/_test/tenant-context')->assertOk()->assertExactJson([
        'tenant_id' => '', 'user_id' => '', 'role' => '', 'current_tenant' => null,
    ]);
    expectTenantContextCleared();
});

test('TenantContext is cleared when downstream middleware throws', function () {
    $user = User::factory()->create();
    $request = Request::create('/_test/exception');
    $request->setLaravelSession(app('session')->driver());
    $request->setUserResolver(fn () => $user);
    $failure = new RuntimeException('Downstream failure');

    try {
        app(SetTenantContext::class)->handle($request, function () use ($user, $failure) {
            expect(tenantContextSnapshot()['tenant_id'])->toBe($user->tenant_id);
            throw $failure;
        });
        $this->fail('Expected downstream exception');
    } catch (RuntimeException $exception) {
        expect($exception)->toBe($failure);
    }
    expectTenantContextCleared();
});

test('TenantContext isolates sequential requests for different tenants on the same connection', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $pdo = DB::connection()->getPdo();

    foreach ([$first, $second] as $user) {
        $this->actingAs($user)->get('/_test/tenant-context')->assertOk()->assertExactJson([
            'tenant_id' => $user->tenant_id, 'user_id' => (string) $user->id,
            'role' => 'user', 'current_tenant' => $user->tenant_id,
        ]);
        expectTenantContextCleared();
        expect(DB::connection()->getPdo())->toBe($pdo);
    }
});

test('TenantContext clears context set during login after the request ends', function () {
    $user = User::factory()->create();
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();
    $this->assertAuthenticatedAs($user);
    expectTenantContextCleared();
});
