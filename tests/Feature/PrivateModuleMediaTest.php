<?php

use App\Enums\UserRole;
use App\Models\ModuleAssignment;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use App\Models\UserModuleAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    Storage::disk('private')->put('module-media/lesson.png', 'private image');
    $this->url = route('platform.media.serve', 'lesson.png');
    $this->tenant = Tenant::factory()->create();
    $this->learner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => UserRole::User]);
    $package = Package::create([
        'name' => 'Media', 'slug' => 'media', 'price_monthly' => 100,
        'max_users' => 100, 'includes_all_modules' => true, 'is_active' => true,
    ]);
    $this->subscription = Subscription::create([
        'tenant_id' => $this->tenant->id, 'package_id' => $package->id,
        'status' => 'active', 'started_at' => now(),
    ]);
    $this->module = TrainingModule::create([
        'title' => 'Media', 'content_html' => '<p><img src="'.$this->url.'" alt="Materi" /></p>',
        'duration_minutes' => 10, 'status' => 'published', 'is_active' => true,
    ]);
    $this->assignment = ModuleAssignment::create([
        'user_id' => $this->learner->id, 'tenant_id' => $this->tenant->id,
        'training_module_id' => $this->module->id, 'status' => 'assigned',
    ]);
});

test('assigned learner can read private module images without caching', function (bool $relative) {
    if ($relative) {
        $this->module->update(['content_html' => '<img src="/platform/media/lesson.png" alt="Materi" />']);
    }

    $response = $this->actingAs($this->learner)->get($this->url)->assertOk();
    expect($response->streamedContent())->toBe('private image');
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
})->with([false, true]);

test('learner page receives sanitized rich content without breaking private image reference', function () {
    $this->module->update([
        'content_html' => '<p>Materi aman</p><img src="/platform/media/lesson.png" onerror="alert(1)">',
    ]);

    $this->actingAs($this->learner)->get(route('user.training.show', $this->assignment))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('module.content_html', fn (string $html) => str_contains($html, '/platform/media/lesson.png')
                && str_contains($html, 'Materi aman')
                && ! str_contains($html, 'onerror'))
        );
});

test('private module images reject unauthorized access', function (string $scenario) {
    switch ($scenario) {
        case 'other learner':
            $this->learner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => UserRole::User]);
            break;
        case 'wrong tenant assignment':
            $this->assignment->update(['tenant_id' => Tenant::factory()->create()->id]);
            break;
        case 'wrong tenant module':
            $this->module->update(['tenant_id' => Tenant::factory()->create()->id]);
            break;
        case 'revoked access':
            UserModuleAccess::create([
                'tenant_id' => $this->tenant->id, 'user_id' => $this->learner->id,
                'training_module_id' => $this->module->id, 'is_allowed' => false,
            ]);
            break;
        case 'removed assignment':
            $this->assignment->delete();
            break;
        case 'revoked entitlement':
            $this->subscription->delete();
            break;
        case 'wrong role':
            $this->learner->update(['role' => UserRole::TenantAdmin]);
            break;
        case 'inactive learner':
            $this->learner->update(['is_active' => false]);
            break;
        case 'unreferenced image':
            $this->module->update(['content_html' => '<p>'.$this->url.'</p>']);
            break;
        case 'filename prefix':
            $this->module->update(['content_html' => '<img src="'.$this->url.'.other.png" />']);
            break;
        case 'external image':
            $this->module->update(['content_html' => '<img src="https://example.org/platform/media/lesson.png" />']);
            break;
    }

    $this->actingAs($this->learner)->get($this->url)->assertForbidden();
})->with([
    'other learner', 'wrong tenant assignment', 'wrong tenant module', 'revoked access',
    'removed assignment', 'revoked entitlement', 'wrong role', 'inactive learner',
    'unreferenced image', 'filename prefix', 'external image',
]);

test('private module images require authentication', function () {
    $this->get($this->url)->assertRedirect(route('login'));
});

test('platform admin can still preview unassigned private images', function () {
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'tenant_id' => null]);
    $this->assignment->delete();
    $this->actingAs($admin)->get($this->url)->assertOk();
    $this->get(route('platform.media.serve', 'missing.png'))->assertNotFound();
});

test('private media checks revocation after a successful read', function () {
    $this->actingAs($this->learner)->get($this->url)->assertOk();
    UserModuleAccess::create([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->learner->id,
        'training_module_id' => $this->module->id, 'is_allowed' => false,
    ]);
    $this->get($this->url)->assertForbidden();
});

test('private module images enforce access with RLS enabled for the test owner', function () {
    $other = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'role' => UserRole::User]);
    $role = DB::selectOne('SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user');
    expect($role->rolsuper)->toBeFalse()->and($role->rolbypassrls)->toBeFalse();
    // FORCE makes the existing policies apply to the test owner, too.
    // Roll back these test-only DDL changes with the nested transaction.
    DB::beginTransaction();
    try {
        foreach (['training_modules', 'module_assignments', 'user_module_access', 'tenants', 'subscriptions'] as $table) {
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        }
        $this->actingAs($this->learner)->get($this->url)->assertOk();
        $this->actingAs($other)->get($this->url)->assertForbidden();
    } finally {
        DB::rollBack();
    }
});
