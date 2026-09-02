<?php

use App\Models\Tenant;
use App\Models\User;

test('dry-run tidak menghapus apa pun', function () {
    User::factory()->superAdmin()->create();
    User::factory()->create();

    $this->artisan('app:reset-user-data', ['--dry-run' => true])->assertExitCode(0);

    $this->assertDatabaseCount('users', 2);
});

test('reset menghapus user non-super dan mempertahankan super admin', function () {
    $super = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    User::factory()->count(2)->create(['tenant_id' => $tenant->id]);

    $this->artisan('app:reset-user-data', ['--force' => true])->assertExitCode(0);

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseHas('users', ['id' => $super->id]);
    $this->assertDatabaseCount('tenants', 1);
});

test('with-tenants menghapus tenants juga', function () {
    User::factory()->superAdmin()->create();
    Tenant::factory()->create();

    $this->artisan('app:reset-user-data', ['--force' => true, '--with-tenants' => true])->assertExitCode(0);

    $this->assertDatabaseCount('tenants', 0);
    $this->assertDatabaseCount('users', 1);
});