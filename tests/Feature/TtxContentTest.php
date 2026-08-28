<?php

use App\Models\Tenant;
use App\Models\TtxPlaybook;
use App\Models\User;

test('tenant admin can create playbook', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->post(route('tenant.ttx.playbooks.store'), [
            'title' => 'Playbook Ransomware',
            'description' => 'Prosedur respons ransomware.',
        ])
        ->assertRedirect(route('tenant.ttx.index'));

    $this->assertDatabaseHas('ttx_playbooks', ['title' => 'Playbook Ransomware', 'tenant_id' => $tenant->id]);
});

test('tenant admin can create runbook with steps split by line', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->post(route('tenant.ttx.runbooks.store'), [
            'title' => 'Runbook Isolasi',
            'steps' => "Isolasi host\nPutuskan jaringan\nRestore backup",
        ])
        ->assertRedirect();

    $rb = TtxPlaybook::query()->first(); // hanya memastikan redirect ok
    $runbook = \App\Models\TtxRunbook::where('title', 'Runbook Isolasi')->first();

    expect($runbook->steps)->toHaveCount(3);
});

test('regular user cannot access ttx management', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->get(route('tenant.ttx.index'))->assertForbidden();
});

test('tenant isolation: admin B tidak melihat playbook tenant A', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    TtxPlaybook::create(['tenant_id' => $tenantA->id, 'title' => 'PB A']);

    $adminB = User::factory()->tenantAdmin()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($adminB)
        ->get(route('tenant.ttx.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('playbooks', 0));
});