<?php

use App\Models\ModuleAssignment;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();
    $this->own = TrainingModule::create([
        'tenant_id' => $this->tenantA->id, 'title' => 'Tenant A private',
        'content' => 'Materi A', 'status' => 'published', 'is_active' => true,
    ]);
    $this->foreign = TrainingModule::create([
        'tenant_id' => $this->tenantB->id, 'title' => 'Tenant B private',
        'content' => 'Materi B', 'status' => 'published', 'is_active' => true,
    ]);
    $this->global = TrainingModule::create([
        'title' => 'Global', 'content' => 'Materi global', 'status' => 'published', 'is_active' => true,
    ]);
    $this->inactive = TrainingModule::create([
        'tenant_id' => $this->tenantA->id, 'title' => 'Inactive',
        'content' => 'Materi nonaktif', 'status' => 'draft', 'is_active' => false,
    ]);

    $role = DB::selectOne('SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user');
    expect($role->rolsuper)->toBeFalse()->and($role->rolbypassrls)->toBeFalse();
    // Apply real PostgreSQL policies to the test owner; RefreshDatabase rolls back this DDL.
    DB::statement('ALTER TABLE training_modules FORCE ROW LEVEL SECURITY');
    DB::statement("SELECT set_config('app.role', 'user', false)");
    DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$this->tenantA->id]);
});

test('TrainingModule RLS limits tenant selects to active own and global modules', function (string $role) {
    DB::statement("SELECT set_config('app.role', ?, false)", [$role]);
    expect(TrainingModule::pluck('id')->all())->toEqualCanonicalizing([$this->own->id, $this->global->id])
        ->and(DB::select('SELECT * FROM training_modules WHERE id = ?', [$this->foreign->id]))->toBe([])
        ->and(TrainingModule::find($this->inactive->id))->toBeNull();

    DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$this->tenantB->id]);
    expect(TrainingModule::pluck('id')->all())->toEqualCanonicalizing([$this->foreign->id, $this->global->id]);
})->with(['user', 'tenant_admin']);

test('TrainingModule RLS does not expose private modules without tenant context', function () {
    DB::statement("SELECT set_config('app.tenant_id', '', false)");
    DB::statement("SELECT set_config('app.role', '', false)");
    expect(TrainingModule::pluck('id')->all())->toBe([$this->global->id]);
});

test('TrainingModule RLS migration restores its previous policy on rollback and reapplies isolation', function () {
    // Keep migration DDL on the test transaction even when an admin connection is configured.
    config(['database.connections.pgsql_admin.password' => null]);
    $migration = require database_path('migrations/2026_09_15_000000_harden_training_modules_tenant_rls.php');
    $migration->down();
    expect(TrainingModule::find($this->foreign->id))->not->toBeNull();
    $migration->up();
    expect(TrainingModule::find($this->foreign->id))->toBeNull()
        ->and(TrainingModule::pluck('id')->all())->toEqualCanonicalizing([$this->own->id, $this->global->id]);
});

test('TrainingModule RLS preserves platform admin reads and writes across tenants', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin)->get(route('platform.modules.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('modules', 4));
    $this->get(route('platform.modules.show', $this->foreign))->assertOk();

    // HTTP middleware clears context; direct service queries need their own platform context.
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
    expect(TrainingModule::whereKey($this->foreign->id)->update(['title' => 'Updated']))->toBe(1);
    $created = TrainingModule::create([
        'tenant_id' => $this->tenantB->id, 'title' => 'Created by platform', 'content' => 'Materi',
    ]);
    expect($created->delete())->toBeTrue();
});

test('TrainingModule RLS does not let tenant roles delete modules through the ALL policy', function () {
    expect(TrainingModule::whereKey($this->foreign->id)->delete())->toBe(0)
        ->and(TrainingModule::whereKey($this->own->id)->delete())->toBe(0);
});

test('TrainingModule RLS filters tenant and learner listings even with cached foreign module ids', function (bool $allModules) {
    $package = Package::create([
        'name' => 'RLS', 'slug' => 'rls', 'price_monthly' => 100,
        'features' => ['training'], 'includes_all_modules' => $allModules,
    ]);
    $package->modules()->attach([$this->own->id, $this->foreign->id, $this->global->id]);
    $subscription = Subscription::create([
        'tenant_id' => $this->tenantA->id, 'package_id' => $package->id,
        'status' => 'active', 'started_at' => now()->subDay(),
    ]);
    // Simulate a cache populated before the RLS fix or in platform context.
    Cache::put("entitlement:module_ids:{$this->tenantA->id}:{$subscription->id}:{$package->id}",
        [$this->own->id, $this->foreign->id, $this->global->id], 300);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $this->tenantA->id]);
    $learner = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => 'user']);
    foreach ([$this->own, $this->foreign, $this->global] as $module) {
        ModuleAssignment::create([
            'tenant_id' => $this->tenantA->id, 'user_id' => $learner->id,
            'training_module_id' => $module->id, 'status' => 'assigned',
        ]);
    }

    $this->actingAs($admin)->get(route('tenant.assignments.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('modules', 2)
            ->where('modules', fn ($modules) => collect($modules)->pluck('id')->sort()->values()->all()
                === collect([$this->own->id, $this->global->id])->sort()->values()->all()));
    $this->actingAs($learner)->get(route('user.training.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('assignments', 2)
            ->where('assignments', fn ($assignments) => collect($assignments)->pluck('training_module_id')->sort()->values()->all()
                === collect([$this->own->id, $this->global->id])->sort()->values()->all()));
    $foreignAssignment = ModuleAssignment::where('training_module_id', $this->foreign->id)->first();
    $this->get(route('user.training.show', $foreignAssignment))->assertForbidden();
    $this->get(route('platform.modules.index'))->assertForbidden();
})->with(['curated' => false, 'all modules' => true]);
