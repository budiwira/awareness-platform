<?php

use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TtxExercise;
use App\Models\TtxInject;
use App\Models\TtxScenarioInjectTemplate;
use App\Models\TtxScenarioTemplate;
use App\Models\User;
use App\Services\TtxScenarioCatalogService;
use App\Services\TtxSessionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function catalogTemplate(string $status = 'draft', array $codes = ['EX-1']): TtxScenarioTemplate
{
    return TtxScenarioTemplate::create([
        'title' => 'Insiden Akun',
        'scenario' => 'Aktivitas akun yang tidak biasa perlu divalidasi dan ditangani.',
        'objectives' => 'Latih koordinasi tim.',
        'scope' => 'Respons insiden',
        'capability_codes' => $codes,
        'status' => $status,
    ]);
}

function catalogInject(TtxScenarioTemplate $template, int $order = 1, array $codes = ['EX-1']): TtxScenarioInjectTemplate
{
    return $template->injects()->create([
        'order' => $order,
        'title' => 'Sinyal awal '.$order,
        'description' => 'Tim melihat aktivitas yang perlu ditinjau.',
        'capability_codes' => $codes,
        'status' => 'active',
    ]);
}

function catalogTenantAdmin(): array
{
    $tenant = Tenant::factory()->create();
    $package = Package::create([
        'name' => 'Tabletop Catalog', 'slug' => 'ttx-catalog-'.uniqid(),
        'price_monthly' => 100, 'features' => ['ttx'], 'is_active' => true,
    ]);
    Subscription::create([
        'tenant_id' => $tenant->id, 'package_id' => $package->id,
        'status' => 'active', 'started_at' => now()->subDay(),
    ]);

    return [$tenant, User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id])];
}

test('platform catalog lists only global templates and never tenant exercises', function () {
    $tenant = Tenant::factory()->create();
    TtxExercise::create(['tenant_id' => $tenant->id, 'title' => 'Private tenant exercise']);
    catalogTemplate();
    $admin = User::factory()->superAdmin()->create();

    $response = $this->actingAs($admin)->get(route('platform.ttx.scenarios.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Platform/Ttx/Scenarios/Index')
            ->has('scenarios', 1)->where('scenarios.0.title', 'Insiden Akun'));
    expect($response->getContent())->not->toContain('Private tenant exercise');
});

test('platform admin creates edits and publishes a valid scenario with canonical capabilities', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin)->post(route('platform.ttx.scenarios.store'), [
        'title' => 'Latihan Akun', 'scenario' => 'Akun dicurigai disalahgunakan.',
        'capability_codes' => ['EX-1', 'EX-1', 'EX-2'],
    ])->assertRedirect();
    $template = TtxScenarioTemplate::sole();
    expect($template->status)->toBe('draft')->and($template->capability_codes)->toBe(['EX-1', 'EX-2']);

    $this->put(route('platform.ttx.scenarios.update', $template), [
        'title' => 'Latihan Akun Baru', 'scenario' => 'Tim meninjau akses yang mencurigakan.',
        'capability_codes' => ['EX-1', 'EX-2'],
    ])->assertRedirect();
    $this->post(route('platform.ttx.scenarios.injects.store', $template), [
        'title' => 'Sinyal awal', 'description' => 'Ada login asing.', 'capability_codes' => ['EX-1'],
    ])->assertRedirect();
    $this->post(route('platform.ttx.scenarios.publish', $template))->assertRedirect();

    expect($template->fresh()->status)->toBe('published');
    foreach (['ttx.scenario_template_created', 'ttx.scenario_template_updated', 'ttx.scenario_inject_created', 'ttx.scenario_template_published'] as $action) {
        $this->assertDatabaseHas('audit_logs', ['action' => $action]);
    }
});

test('publish enforces complete canonical scenario and inject semantics', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $template = catalogTemplate(codes: []);
    $this->post(route('platform.ttx.scenarios.publish', $template))->assertSessionHasErrors('scenario');
    $template->update(['capability_codes' => ['EX-1']]);
    $this->post(route('platform.ttx.scenarios.publish', $template))->assertSessionHasErrors('injects');
    $inject = catalogInject($template, codes: ['EX-2']);
    $this->post(route('platform.ttx.scenarios.publish', $template))->assertSessionHasErrors('injects');
    $inject->update(['capability_codes' => []]);
    $this->post(route('platform.ttx.scenarios.publish', $template))->assertSessionHasErrors('injects');
    expect($template->fresh()->status)->toBe('draft');

    $this->put(route('platform.ttx.scenarios.injects.update', [$template, $inject]), [
        'title' => 'Invalid', 'capability_codes' => ['EX-99'],
    ])->assertSessionHasErrors('capability_codes.0');
    $this->put(route('platform.ttx.scenarios.update', $template), [
        'title' => 'Invalid', 'capability_codes' => ['EX-99'],
    ])->assertSessionHasErrors('capability_codes.0');
});

test('inject editing archiving and reorder are scoped and preserve contiguous order', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $template = catalogTemplate();
    $first = catalogInject($template);
    $second = catalogInject($template, 2);
    $other = catalogTemplate();
    $foreign = catalogInject($other);

    $this->put(route('platform.ttx.scenarios.injects.update', [$template, $foreign]), [
        'title' => 'Spoof', 'capability_codes' => ['EX-1'],
    ])->assertNotFound();
    $this->delete(route('platform.ttx.scenarios.injects.archive', [$template, $foreign]))->assertNotFound();
    $this->post(route('platform.ttx.scenarios.injects.reorder', $template), [
        'inject_ids' => [$first->id, $foreign->id],
    ])->assertSessionHasErrors('inject_ids');
    $this->post(route('platform.ttx.scenarios.injects.reorder', $template), [
        'inject_ids' => [$first->id, $first->id],
    ])->assertSessionHasErrors('inject_ids');
    $this->post(route('platform.ttx.scenarios.injects.reorder', $template), [
        'inject_ids' => [$second->id, $first->id],
    ])->assertRedirect();
    expect($template->injects()->where('status', 'active')->orderBy('order')->pluck('id')->all())->toBe([$second->id, $first->id]);

    $this->put(route('platform.ttx.scenarios.injects.update', [$template, $second]), [
        'title' => 'Sinyal diperbarui', 'capability_codes' => ['EX-1', 'EX-1'],
    ])->assertRedirect();
    expect($second->fresh()->capability_codes)->toBe(['EX-1']);
    $this->delete(route('platform.ttx.scenarios.injects.archive', [$template, $second]))->assertRedirect();
    expect($second->fresh()->status)->toBe('archived')
        ->and($first->fresh()->order)->toBe(1);
    foreach (['ttx.scenario_inject_updated', 'ttx.scenario_inject_archived', 'ttx.scenario_inject_reordered'] as $action) {
        $this->assertDatabaseHas('audit_logs', ['action' => $action]);
    }
});

test('archived scenarios are retained and unavailable to tenants', function () {
    $template = catalogTemplate('published');
    catalogInject($template);
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin)->post(route('platform.ttx.scenarios.archive', $template))->assertRedirect();
    expect($template->fresh()->status)->toBe('archived');
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.scenario_template_archived']);
    [$tenant, $tenantAdmin] = catalogTenantAdmin();
    $this->actingAs($tenantAdmin)->get(route('tenant.ttx.scenarios.index'))
        ->assertInertia(fn (Assert $page) => $page->has('scenarios', 0));
    $this->post(route('tenant.ttx.scenarios.instantiate', $template))->assertNotFound();
});

test('published scenario cannot lose its only active inject', function () {
    $template = catalogTemplate('published');
    $inject = catalogInject($template);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->delete(route('platform.ttx.scenarios.injects.archive', [$template, $inject]))
        ->assertSessionHasErrors('injects');
    expect($inject->fresh()->status)->toBe('active');
    $this->assertDatabaseMissing('audit_logs', ['action' => 'ttx.scenario_inject_archived']);
});

test('tenant sees only published templates and cannot use draft archived or archived injects', function () {
    [$tenant, $admin] = catalogTenantAdmin();
    $published = catalogTemplate('published');
    $active = catalogInject($published);
    $archived = catalogInject($published, 2);
    $archived->update(['status' => 'archived']);
    $draft = catalogTemplate();
    catalogInject($draft);
    $hidden = catalogTemplate('archived');

    $response = $this->actingAs($admin)->get(route('tenant.ttx.scenarios.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Tenant/Ttx/Scenarios/Index')
            ->has('scenarios', 1)->where('scenarios.0.active_inject_count', 1));
    expect($response->getContent())->not->toContain('"status":"draft"');
    $this->post(route('tenant.ttx.scenarios.instantiate', $draft))->assertNotFound();
    $this->post(route('tenant.ttx.scenarios.instantiate', $hidden))->assertNotFound();
    $this->post(route('tenant.ttx.scenarios.instantiate', $published), ['tenant_id' => Tenant::factory()->create()->id])->assertRedirect();
    expect(TtxInject::count())->toBe(1)->and(TtxInject::sole()->title)->toBe($active->title);
});

test('instantiation derives tenant ownership copies semantics and preserves custom exercise flow', function () {
    [$tenant, $admin] = catalogTenantAdmin();
    $foreign = Tenant::factory()->create();
    $template = catalogTemplate('published', ['EX-1', 'EX-2']);
    $first = catalogInject($template, 1, ['EX-1']);
    $second = catalogInject($template, 2, ['EX-2']);

    $this->actingAs($admin)->post(route('tenant.ttx.scenarios.instantiate', $template), [
        'tenant_id' => $foreign->id, 'playbook_id' => 99999,
        'inject_ids' => [$second->id], 'source_scenario_template_id' => 99999,
    ])->assertRedirect();
    $exercise = TtxExercise::sole();
    expect($exercise->tenant_id)->toBe($tenant->id)
        ->and($exercise->source_scenario_template_id)->toBe($template->id)
        ->and($exercise->phase)->toBe('planning')
        ->and($exercise->playbook_id)->toBeNull()
        ->and($exercise->runbook_id)->toBeNull()
        ->and($exercise->capability_codes)->toBe(['EX-1', 'EX-2'])
        ->and($exercise->scenario)->toBe($template->scenario)
        ->and($exercise->objectives)->toBe($template->objectives)
        ->and($exercise->scope)->toBe($template->scope)
        ->and($exercise->injects()->pluck('order')->all())->toBe([1, 2]);
    expect($exercise->injects()->pluck('capability_codes')->all())->toBe([['EX-1'], ['EX-2']]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ttx.scenario_template_instantiated', 'tenant_id' => $tenant->id]);

    $this->post(route('tenant.ttx.exercises.store'), ['title' => 'Exercise Kustom'])->assertRedirect();
    expect(TtxExercise::where('title', 'Exercise Kustom')->sole()->source_scenario_template_id)->toBeNull();
});

test('tenant instantiates a published template with catalog RLS enforced', function () {
    [$tenant, $admin] = catalogTenantAdmin();
    $template = catalogTemplate('published');
    catalogInject($template);
    DB::statement('ALTER TABLE ttx_scenario_templates FORCE ROW LEVEL SECURITY');
    DB::statement('ALTER TABLE ttx_scenario_inject_templates FORCE ROW LEVEL SECURITY');

    $this->actingAs($admin)->post(route('tenant.ttx.scenarios.instantiate', $template))->assertRedirect();
    expect(TtxExercise::sole()->tenant_id)->toBe($tenant->id);
});

test('failed inject copy rolls back exercise and earlier inject copies', function () {
    [$tenant, $admin] = catalogTenantAdmin();
    $template = catalogTemplate('published');
    catalogInject($template);
    catalogInject($template, 2);

    TtxInject::creating(function (TtxInject $inject): void {
        if ($inject->order === 2) {
            throw new RuntimeException('QA inject copy failure');
        }
    });

    try {
        $this->withoutExceptionHandling();
        expect(fn () => $this->actingAs($admin)->post(route('tenant.ttx.scenarios.instantiate', $template)))
            ->toThrow(RuntimeException::class, 'QA inject copy failure');
        expect(TtxExercise::count())->toBe(0)
            ->and(TtxInject::count())->toBe(0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'ttx.scenario_template_instantiated']);
    } finally {
        TtxInject::flushEventListeners();
    }
});

test('nested transaction copies one source state when template changes after its read', function () {
    [$tenant, $admin] = catalogTenantAdmin();
    $platform = User::factory()->superAdmin()->create();
    $template = catalogTemplate('published');
    $first = catalogInject($template);
    $second = catalogInject($template, 2);
    $this->actingAs($admin);
    DB::statement("SELECT set_config('app.role', 'tenant_admin', false)");
    DB::statement("SELECT set_config('app.user_id', ?, false)", [(string) $admin->id]);
    DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$tenant->id]);

    TtxScenarioTemplate::retrieved(function (TtxScenarioTemplate $loaded) use ($template, $first, $second, $platform, $admin): void {
        if ($loaded->id !== $template->id) {
            return;
        }
        DB::statement("SELECT set_config('app.role', 'super_admin', false)");
        DB::statement("SELECT set_config('app.user_id', ?, false)", [(string) $platform->id]);
        DB::table('ttx_scenario_templates')->where('id', $template->id)->update(['status' => 'archived', 'title' => 'Revisi']);
        DB::table('ttx_scenario_inject_templates')->where('id', $first->id)->update(['title' => 'Inject revisi']);
        DB::table('ttx_scenario_inject_templates')->where('id', $second->id)->update(['status' => 'archived']);
        DB::statement("SELECT set_config('app.role', 'tenant_admin', false)");
        DB::statement("SELECT set_config('app.user_id', ?, false)", [(string) $admin->id]);
    });

    try {
        $exercise = app(TtxScenarioCatalogService::class)->instantiate($admin, $template);
        expect($exercise->title)->toBe($template->title)
            ->and($exercise->injects()->orderBy('order')->pluck('title')->all())->toBe([$first->title, $second->title]);
    } finally {
        TtxScenarioTemplate::flushEventListeners();
    }
});

test('template edits do not mutate tenant copies or existing session snapshots', function () {
    [$tenant, $admin] = catalogTenantAdmin();
    $template = catalogTemplate('published');
    $inject = catalogInject($template);
    $this->actingAs($admin)->post(route('tenant.ttx.scenarios.instantiate', $template))->assertRedirect();
    $exercise = TtxExercise::sole();
    attachTtxTestPlaybook($exercise);
    $session = app(TtxSessionService::class)->create($admin, $exercise->fresh(), 'Sesi asli');
    $beforeExercise = $exercise->fresh()->scenario;
    $beforeInject = $exercise->injects()->first()->description;
    $beforeSession = $session->fresh()->exercise_snapshot;
    $beforeSessionInject = $session->injects()->first()->inject_snapshot;
    $beforePlaybook = $session->fresh()->playbook_snapshot;

    $platform = User::factory()->superAdmin()->create();
    $this->actingAs($platform)->put(route('platform.ttx.scenarios.update', $template), [
        'title' => 'Revisi', 'scenario' => 'Narasi revisi', 'capability_codes' => ['EX-1'],
    ])->assertRedirect();
    $this->put(route('platform.ttx.scenarios.injects.update', [$template, $inject]), [
        'title' => 'Inject revisi', 'description' => 'Isi revisi', 'capability_codes' => ['EX-1'],
    ])->assertRedirect();
    expect($exercise->fresh()->scenario)->toBe($beforeExercise)
        ->and($exercise->injects()->first()->description)->toBe($beforeInject)
        ->and($session->fresh()->exercise_snapshot)->toBe($beforeSession)
        ->and($session->injects()->first()->inject_snapshot)->toBe($beforeSessionInject)
        ->and($session->fresh()->playbook_snapshot)->toBe($beforePlaybook);
});

test('tenant admin learner and inactive platform admin cannot author catalog', function () {
    [$tenant, $tenantAdmin] = catalogTenantAdmin();
    $template = catalogTemplate();
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $inactive = User::factory()->superAdmin()->create(['is_active' => false]);
    foreach ([$tenantAdmin, $learner, $inactive] as $actor) {
        $this->actingAs($actor)->post(route('platform.ttx.scenarios.store'), [
            'title' => 'Unauthorized', 'capability_codes' => ['EX-1'],
        ])->assertForbidden();
        $this->get(route('platform.ttx.scenarios.index'))->assertForbidden();
    }
    $this->actingAs($learner)->get(route('tenant.ttx.scenarios.index'))->assertForbidden();
    $this->post(route('tenant.ttx.scenarios.instantiate', $template))->assertForbidden();
    $this->actingAs($inactive)->get(route('tenant.ttx.scenarios.index'))->assertForbidden();
    expect(TtxScenarioTemplate::count())->toBe(1);
});

test('direct PostgreSQL RLS separates platform authoring tenant published reads and learner denial', function () {
    $draft = catalogTemplate();
    $draftInject = catalogInject($draft);
    $published = catalogTemplate('published');
    $active = catalogInject($published);
    $archived = catalogInject($published, 2);
    $archived->update(['status' => 'archived']);
    $platformAdmin = User::factory()->superAdmin()->create();
    $inactivePlatformAdmin = User::factory()->superAdmin()->create(['is_active' => false]);

    $role = DB::selectOne('SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user');
    expect($role->rolsuper)->toBeFalse()->and($role->rolbypassrls)->toBeFalse();
    DB::statement('ALTER TABLE ttx_scenario_templates FORCE ROW LEVEL SECURITY');
    DB::statement('ALTER TABLE ttx_scenario_inject_templates FORCE ROW LEVEL SECURITY');

    DB::statement("SELECT set_config('app.role', 'tenant_admin', false)");
    expect(TtxScenarioTemplate::pluck('id')->all())->toBe([$published->id])
        ->and(TtxScenarioInjectTemplate::pluck('id')->all())->toBe([$active->id])
        ->and(TtxScenarioTemplate::find($draft->id))->toBeNull()
        ->and(TtxScenarioInjectTemplate::find($draftInject->id))->toBeNull()
        ->and(TtxScenarioTemplate::whereKey($published->id)->update(['title' => 'Spoof']))->toBe(0)
        ->and(TtxScenarioInjectTemplate::whereKey($active->id)->update(['title' => 'Spoof']))->toBe(0);
    expect(fn () => DB::transaction(fn () => catalogTemplate()))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => catalogInject($published, 3)))->toThrow(QueryException::class);

    DB::statement("SELECT set_config('app.role', 'user', false)");
    expect(TtxScenarioTemplate::count())->toBe(0)->and(TtxScenarioInjectTemplate::count())->toBe(0);

    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
    DB::statement("SELECT set_config('app.user_id', ?, false)", [(string) $inactivePlatformAdmin->id]);
    expect(TtxScenarioTemplate::count())->toBe(0)->and(TtxScenarioInjectTemplate::count())->toBe(0);
    DB::statement("SELECT set_config('app.user_id', ?, false)", [(string) $platformAdmin->id]);
    expect(TtxScenarioTemplate::count())->toBe(2)
        ->and(TtxScenarioInjectTemplate::count())->toBe(3)
        ->and(TtxScenarioTemplate::whereKey($draft->id)->update(['title' => 'Platform edit']))->toBe(1);
});
