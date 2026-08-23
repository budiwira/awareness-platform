<?php

use App\Models\Tenant;
use App\Models\User;

test('tenant admin can import users via CSV', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

    $csvContent = "name,email,role\nBudi,budi@test.local,user\nSiti,siti@test.local,tenant_admin\n";
    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('users.csv', $csvContent);

    $this->actingAs($admin)
        ->post(route('tenant.users.import'), ['file' => $file])
        ->assertRedirect(route('tenant.users.index'));

    $this->assertDatabaseHas('users', ['email' => 'budi@test.local', 'tenant_id' => $tenant->id]);
    $this->assertDatabaseHas('users', ['email' => 'siti@test.local', 'tenant_id' => $tenant->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.bulk_imported']);
});

test('CSV injection is sanitized', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

    // Payload berbahaya Excel Formula Injection
    $csvContent = "name,email,role\n=cmd|' /C calc'!A0,evil@test.local,user\n";
    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('evil.csv', $csvContent);

    $this->actingAs($admin)
        ->post(route('tenant.users.import'), ['file' => $file])
        ->assertRedirect();

    // Nama harus diawali tanda kutip satu
    $user = User::where('email', 'evil@test.local')->first();
    expect($user->name)->toStartWith("'");
});

test('invalid role in CSV rejects entire import', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

    $csvContent = "name,email,role\nBudi,budi@test.local,super_admin\n";
    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('bad.csv', $csvContent);

    $this->actingAs($admin)
        ->post(route('tenant.users.import'), ['file' => $file])
        ->assertSessionHasErrors('file');

    $this->assertDatabaseMissing('users', ['email' => 'budi@test.local']);
});