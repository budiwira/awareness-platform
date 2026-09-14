<?php

use App\Enums\UserRole;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('invariant: upload gambar di private disk, bukan public', function () {
    Storage::fake('private');

    $tenant = Tenant::factory()->create();
    $package = Package::create([
        'name' => 'Test-'.uniqid(),
        'slug' => 'test-'.uniqid(),
        'price_monthly' => 100,
        'max_users' => 100,
        'features' => ['training'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    Subscription::create([
        'tenant_id' => $tenant->id,
        'package_id' => $package->id,
        'status' => 'active',
        'started_at' => now(),
    ]);

    // SuperAdmin = cross-tenant, tenant_id HARUS null
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'tenant_id' => null]);
    $module = TrainingModule::create([
        'title' => 'Test',
        'content' => 'x',
        'duration_minutes' => 10,
        'status' => 'published',
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->create('test.jpg', 1024, 'image/jpeg');

    $response = $this->actingAs($admin)
        ->postJson(route('platform.modules.media.store', $module), ['file' => $file])
        ->assertOk()
        ->assertJsonStructure(['url']);

    Storage::disk('private')->assertExists('module-media/'.basename(parse_url($response->json('url'), PHP_URL_PATH)));
    Storage::disk('public')->assertMissing('module-media/'.basename(parse_url($response->json('url'), PHP_URL_PATH)));
});

test('invariant: upload ditolak jika melebihi 5MB', function () {
    Storage::fake('private');

    $tenant = Tenant::factory()->create();
    $package = Package::create([
        'name' => 'Test-'.uniqid(),
        'slug' => 'test-'.uniqid(),
        'price_monthly' => 100,
        'max_users' => 100,
        'features' => ['training'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    Subscription::create([
        'tenant_id' => $tenant->id,
        'package_id' => $package->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'tenant_id' => null]);
    $module = TrainingModule::create([
        'title' => 'Test',
        'content' => 'x',
        'duration_minutes' => 10,
        'status' => 'published',
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->create('test.jpg', 6000, 'image/jpeg');

    $this->actingAs($admin)
        ->postJson(route('platform.modules.media.store', $module), ['file' => $file])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

test('invariant: upload ditolak jika bukan gambar (MIME check)', function () {
    Storage::fake('private');

    $tenant = Tenant::factory()->create();
    $package = Package::create([
        'name' => 'Test-'.uniqid(),
        'slug' => 'test-'.uniqid(),
        'price_monthly' => 100,
        'max_users' => 100,
        'features' => ['training'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    Subscription::create([
        'tenant_id' => $tenant->id,
        'package_id' => $package->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'tenant_id' => null]);
    $module = TrainingModule::create([
        'title' => 'Test',
        'content' => 'x',
        'duration_minutes' => 10,
        'status' => 'published',
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    $this->actingAs($admin)
        ->postJson(route('platform.modules.media.store', $module), ['file' => $file])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

test('invariant: content_html disanitize, content = strip_tags', function () {
    $tenant = Tenant::factory()->create();
    $package = Package::create([
        'name' => 'Test-'.uniqid(),
        'slug' => 'test-'.uniqid(),
        'price_monthly' => 100,
        'max_users' => 100,
        'features' => ['training'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    Subscription::create([
        'tenant_id' => $tenant->id,
        'package_id' => $package->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'tenant_id' => null]);

    $response = $this->actingAs($admin)
        ->postJson(route('platform.modules.store'), [
            'title' => 'Test XSS',
            'description' => 'Desc',
            'content_html' => '<p>Halo</p><script>alert(1)</script>',
            'duration_minutes' => 10,
            'status' => 'draft',
        ])
        ->assertRedirect();

    $module = TrainingModule::latest()->first();

    expect($module->content_html)->not->toContain('<script>')
        ->and($module->content)->toBe('Halo');
});

test('invariant: unauthorized user tidak bisa upload media', function () {
    Storage::fake('private');

    $tenant = Tenant::factory()->create();
    $package = Package::create([
        'name' => 'Test-'.uniqid(),
        'slug' => 'test-'.uniqid(),
        'price_monthly' => 100,
        'max_users' => 100,
        'features' => ['training'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    Subscription::create([
        'tenant_id' => $tenant->id,
        'package_id' => $package->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::User]);
    $module = TrainingModule::create([
        'title' => 'Test',
        'content' => 'x',
        'duration_minutes' => 10,
        'status' => 'published',
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->create('test.jpg', 1024, 'image/jpeg');

    $this->actingAs($user)
        ->postJson(route('platform.modules.media.store', $module), ['file' => $file])
        ->assertForbidden();
});

test('invariant: private disk terkonfigurasi di filesystems config', function () {
    // Storage::fake() mendaftarkan disk dinamis dan bisa menyembunyikan config yang hilang.
    // Test ini memverifikasi config nyata, bukan fake.
    // Path Laravel menormalisasi ke forward slash bahkan di Windows.
    $root = config('filesystems.disks.private.root');
    expect(config('filesystems.disks.private.driver'))->toBe('local')
        ->and($root)->toMatch('#[/\\\\]private$#');
});
