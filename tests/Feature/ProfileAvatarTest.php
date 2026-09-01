<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_avatar()
    {
        Storage::fake('public');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);

        $file = UploadedFile::fake()->create('avatar.jpg', 500, 'image/jpeg');

        $response = $this->actingAs($user)->post(route('profile.avatar'), [
            'avatar' => $file,
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status', 'Avatar berhasil diperbarui.');

        $user->refresh();
        expect($user->avatar_path)->not->toBeNull();
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_avatar_rejects_svg()
    {
        Storage::fake('public');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);

        $file = UploadedFile::fake()->create('avatar.svg', 100, 'image/svg+xml');

        $response = $this->actingAs($user)->post(route('profile.avatar'), [
            'avatar' => $file,
        ]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_avatar_rejects_file_over_2mb()
    {
        Storage::fake('public');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);

        $file = UploadedFile::fake()->create('avatar.jpg', 3000, 'image/jpeg');

        $response = $this->actingAs($user)->post(route('profile.avatar'), [
            'avatar' => $file,
        ]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_avatar_accepts_png_webp()
    {
        Storage::fake('public');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);

        $png = UploadedFile::fake()->create('avatar.png', 500, 'image/png');

        $response = $this->actingAs($user)->post(route('profile.avatar'), [
            'avatar' => $png,
        ]);

        $response->assertRedirect(route('profile.edit'));

        $user->refresh();
        expect($user->avatar_path)->not->toBeNull();

        // Test webp
        $user->update(['avatar_path' => null]);
        $webp = UploadedFile::fake()->create('avatar.webp', 500, 'image/webp');

        $response = $this->actingAs($user)->post(route('profile.avatar'), [
            'avatar' => $webp,
        ]);

        $response->assertRedirect(route('profile.edit'));

        $user->refresh();
        expect($user->avatar_path)->not->toBeNull();
    }

    public function test_uploading_new_avatar_deletes_old_file()
    {
        Storage::fake('public');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);

        // Upload pertama
        $file1 = UploadedFile::fake()->create('avatar1.jpg', 500, 'image/jpeg');
        $this->actingAs($user)->post(route('profile.avatar'), ['avatar' => $file1]);

        $user->refresh();
        $oldPath = $user->avatar_path;

        Storage::disk('public')->assertExists($oldPath);

        // Upload kedua
        $file2 = UploadedFile::fake()->create('avatar2.jpg', 500, 'image/jpeg');
        $this->actingAs($user)->post(route('profile.avatar'), ['avatar' => $file2]);

        $user->refresh();
        $newPath = $user->avatar_path;

        // File lama dihapus, file baru ada
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
        expect($oldPath)->not->toBe($newPath);
    }

    public function test_avatar_appears_in_app_layout()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'user',
            'avatar_path' => 'avatars/test.jpg',
        ]);

        $response = $this->actingAs($user)->get(route('user.dashboard'));

        $response->assertOk();
        
        $authUser = $response->viewData('page')['props']['auth']['user'];
        expect($authUser['avatar_path'])->toBe('avatars/test.jpg');
    }

    public function test_fallback_initials_when_no_avatar()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'user',
            'name' => 'John Doe',
            'avatar_path' => null,
        ]);

        $response = $this->actingAs($user)->get(route('user.dashboard'));

        $response->assertOk();
        
        $authUser = $response->viewData('page')['props']['auth']['user'];
        expect($authUser['avatar_path'])->toBeNull();
        expect($authUser['name'])->toBe('John Doe');
        // Frontend computed initials akan jadi 'JD'
    }
}
