<?php

use App\Enums\UserRole;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function setFakePrivateMtime(string $path, int $timestamp): void
{
    $fullPath = Storage::disk('private')->path($path);

    touch($fullPath, $timestamp);
    clearstatcache(true, $fullPath);
}

test('invariant: upload module-agnostic ke private disk', function () {
    Storage::fake('private');

    $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'tenant_id' => null]);
    $file = UploadedFile::fake()->create('test.jpg', 1024, 'image/jpeg');

    $response = $this->actingAs($admin)
        ->postJson(route('platform.media.store'), ['file' => $file])
        ->assertOk()
        ->assertJsonStructure(['url']);

    expect($response->json('url'))->toStartWith('/platform/media/')
        ->not->toContain('://');

    Storage::disk('private')->assertExists('module-media/'.basename(parse_url($response->json('url'), PHP_URL_PATH)));
});

test('invariant: prune command hapus file yatim tua', function () {
    Storage::fake('private');

    $oldFile = 'module-media/old-orphan.jpg';
    Storage::disk('private')->put($oldFile, 'fake content');
    setFakePrivateMtime($oldFile, now()->subDays(30)->timestamp);

    $newFile = 'module-media/new-orphan.jpg';
    Storage::disk('private')->put($newFile, 'fake content');
    setFakePrivateMtime($newFile, now()->subDay()->timestamp);

    $referencedFile = 'module-media/referenced.jpg';
    Storage::disk('private')->put($referencedFile, 'fake content');
    setFakePrivateMtime($referencedFile, now()->subDays(30)->timestamp);

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

    TrainingModule::create([
        'title' => 'Test',
        'content' => 'x',
        'content_html' => '<img src="https://example.com/media/referenced.jpg">',
        'duration_minutes' => 10,
        'status' => 'published',
        'is_active' => true,
    ]);

    $this->artisan('media:prune-orphans', ['--days' => 7])
        ->assertExitCode(0);

    Storage::disk('private')->assertMissing($oldFile);
    Storage::disk('private')->assertExists($newFile);
    Storage::disk('private')->assertExists($referencedFile);
});
