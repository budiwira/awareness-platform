<?php

use App\Models\ModuleAssignment;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use App\Services\TenantEntitlement;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    Cache::flush();
    $this->tenant = Tenant::factory()->create();
    $this->package = Package::create([
        'name' => 'Lifecycle', 'slug' => 'lifecycle', 'price_monthly' => 100,
        'max_users' => 100, 'features' => ['training'], 'includes_all_modules' => false,
    ]);
    $this->module = TrainingModule::create([
        'title' => 'Entitled', 'content' => 'Materi', 'status' => 'published', 'is_active' => true,
    ]);
    $this->outside = TrainingModule::create([
        'title' => 'Outside package', 'content' => 'Materi', 'status' => 'published', 'is_active' => true,
    ]);
    $this->draft = TrainingModule::create([
        'title' => 'Draft', 'content' => 'Materi', 'status' => 'draft', 'is_active' => true,
    ]);
    $this->package->modules()->attach($this->module);
    $this->subscription = Subscription::create([
        'tenant_id' => $this->tenant->id, 'package_id' => $this->package->id,
        'status' => 'active', 'started_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);
});

test('TenantEntitlement enforces subscription lifecycle and preserves package module rules', function (string $scenario, bool $allowed, bool $allModules) {
    $this->package->update(['includes_all_modules' => $allModules]);
    $changes = match ($scenario) {
        'expired' => ['ends_at' => now()->subSecond()],
        'ends now' => ['ends_at' => now()],
        'future' => ['started_at' => now()->addSecond()],
        'starts now' => ['started_at' => now()],
        'cancelled' => ['status' => 'cancelled'],
        'inactive' => ['status' => 'inactive'],
        'open ended' => ['ends_at' => null],
        default => [],
    };
    $this->subscription->update($changes);
    $entitlement = new TenantEntitlement;

    expect($this->tenant->currentSubscription()?->id)->toBe($allowed ? $this->subscription->id : null)
        ->and($entitlement->hasModule($this->tenant, $this->module->id))->toBe($allowed)
        ->and($entitlement->hasModule($this->tenant, $this->outside->id))->toBe($allowed && $allModules)
        ->and($entitlement->hasModule($this->tenant, $this->draft->id))->toBeFalse()
        ->and($entitlement->hasFeature($this->tenant, 'training'))->toBe($allowed);

    $expected = $allowed ? ($allModules ? [$this->module->id, $this->outside->id] : [$this->module->id]) : [];
    expect($entitlement->getEntitledModuleIds($this->tenant))->toEqualCanonicalizing($expected);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']);
    $assignment = ModuleAssignment::create([
        'tenant_id' => $this->tenant->id, 'user_id' => $user->id,
        'training_module_id' => $this->module->id, 'status' => 'assigned',
    ]);
    $this->actingAs($user)->get(route('user.training.show', $assignment))->assertStatus($allowed ? 200 : 403);
})->with([
    'current' => ['current', true],
    'expired' => ['expired', false],
    'ends at boundary' => ['ends now', false],
    'future' => ['future', false],
    'starts at boundary' => ['starts now', true],
    'cancelled' => ['cancelled', false],
    'inactive' => ['inactive', false],
    'null end' => ['open ended', true],
])->with(['curated' => false, 'all modules' => true]);

test('TenantEntitlement does not reuse cached access after subscription becomes invalid', function (string $scenario) {
    $this->subscription->update(['ends_at' => now()->addMinute()]);
    $entitlement = new TenantEntitlement;
    expect($entitlement->getEntitledModuleIds($this->tenant))->toBe([$this->module->id])
        ->and($entitlement->getEntitledFeatures($this->tenant))->toBe(['training']);

    match ($scenario) {
        'expired' => $this->travelTo($this->subscription->ends_at),
        'future' => $this->subscription->update(['started_at' => now()->addDay()]),
        'cancelled' => $this->subscription->update(['status' => 'cancelled']),
    };

    // A fresh service represents the next request; the cache stays warm.
    $nextRequest = new TenantEntitlement;
    expect($nextRequest->hasModule($this->tenant, $this->module->id))->toBeFalse()
        ->and($nextRequest->getEntitledModuleIds($this->tenant))->toBe([])
        ->and($nextRequest->getEntitledFeatures($this->tenant))->toBe([]);
})->with(['expired', 'future', 'cancelled']);

test('TenantEntitlement selects the current package when a newer subscription expires or has not started', function (string $scenario) {
    $otherPackage = Package::create([
        'name' => 'Other', 'slug' => 'other', 'price_monthly' => 200,
        'max_users' => 100, 'features' => ['ctf'], 'includes_all_modules' => true,
    ]);
    $newer = Subscription::create([
        'tenant_id' => $this->tenant->id, 'package_id' => $otherPackage->id,
        'status' => 'active', 'started_at' => now()->subHour(), 'ends_at' => now()->addMinute(),
    ]);
    $entitlement = new TenantEntitlement;
    expect($entitlement->getEntitledModuleIds($this->tenant))->toContain($this->outside->id)
        ->and($entitlement->getEntitledFeatures($this->tenant))->toBe(['ctf']);

    if ($scenario === 'expired') {
        $this->travelTo($newer->ends_at);
    } else {
        $newer->update(['started_at' => now()->addDay()]);
    }

    $nextRequest = new TenantEntitlement;
    expect($this->tenant->currentSubscription()?->id)->toBe($this->subscription->id)
        ->and($nextRequest->getEntitledModuleIds($this->tenant))->toBe([$this->module->id])
        ->and($nextRequest->getEntitledFeatures($this->tenant))->toBe(['training'])
        ->and($nextRequest->hasModule($this->tenant, $this->outside->id))->toBeFalse();
})->with(['expired', 'future']);
