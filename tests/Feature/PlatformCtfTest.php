<?php

use App\Models\CtfChallenge;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('super admin can create challenge with flag', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)
        ->post(route('platform.ctf.store'), [
            'title' => 'Email Misterius',
            'category' => 'phishing',
            'difficulty' => 'beginner',
            'points' => 100,
            'flag' => 'FLAG{jangan_klik_link}',
        ])
        ->assertRedirect(route('platform.ctf.index'));

    $this->assertDatabaseHas('ctf_challenges', ['title' => 'Email Misterius', 'flag' => 'FLAG{jangan_klik_link}']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ctf.created']);
});

test('flag is never exposed in index payload', function () {
    $super = User::factory()->superAdmin()->create();
    CtfChallenge::create(['title' => 'T', 'points' => 100, 'flag' => 'FLAG{rahasia}']);

    $this->actingAs($super)
        ->get(route('platform.ctf.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Ctf/Index')
            ->has('challenges', 1, fn (Assert $c) => $c->missing('flag')->etc())
        );
});

test('tenant admin cannot access ctf management', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->get(route('platform.ctf.index'))->assertForbidden();
});