<?php

use App\Models\CtfChallenge;
use App\Models\CtfSolve;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function makeCtfFixture(): array
{
    $tenant = Tenant::factory()->create();
    $Package = Package::create([
        'name' => 'Ent-'.uniqid(),
        'slug' => 'ent-'.uniqid(),
        'price_monthly' => 100,
        'max_users' => 100,
        'features' => ['ctf'],
        'includes_all_modules' => false,
        'is_active' => true,
    ]);
    Subscription::create([
        'tenant_id' => $tenant->id,
        'package_id' => $Package->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $challenge = CtfChallenge::create([
        'title' => 'Email Misterius',
        'category' => 'phishing',
        'difficulty' => 'beginner',
        'points' => 100,
        'flag' => 'FLAG{jangan_klik_link}',
        'is_active' => true,
    ]);

    return [$user, $challenge];
}

test('challenge payload never contains flag', function () {
    [$user, $challenge] = makeCtfFixture();

    $this->actingAs($user)
        ->get(route('user.ctf.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/Ctf/Index')
            ->has('challenges', 1, fn (Assert $c) => $c->missing('flag')->etc())
        );
});

test('correct flag creates solve with points and audit', function () {
    [$user, $challenge] = makeCtfFixture();

    $this->actingAs($user)
        ->post(route('user.ctf.submit', $challenge), ['flag' => 'FLAG{jangan_klik_link}'])
        ->assertRedirect(route('user.ctf.index'));

    $this->assertDatabaseHas('ctf_solves', [
        'user_id' => $user->id,
        'challenge_id' => $challenge->id,
        'points' => 100,
        'tenant_id' => $user->tenant_id,
    ]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ctf.solved']);
});

test('flag comparison is trimmed and case-insensitive', function () {
    [$user, $challenge] = makeCtfFixture();

    $this->actingAs($user)
        ->post(route('user.ctf.submit', $challenge), ['flag' => '  flag{JANGAN_KLIK_LINK} '])
        ->assertRedirect(route('user.ctf.index'));

    $this->assertDatabaseHas('ctf_solves', ['user_id' => $user->id]);
});

test('wrong flag creates no solve and logs failed attempt', function () {
    [$user, $challenge] = makeCtfFixture();

    $this->actingAs($user)
        ->post(route('user.ctf.submit', $challenge), ['flag' => 'FLAG{salah}'])
        ->assertSessionHasErrors('flag');

    $this->assertDatabaseMissing('ctf_solves', ['user_id' => $user->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'ctf.failed_attempt']);
});

test('duplicate solve is prevented', function () {
    [$user, $challenge] = makeCtfFixture();

    CtfSolve::create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'challenge_id' => $challenge->id,
        'points' => 100,
        'solved_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('user.ctf.submit', $challenge), ['flag' => 'FLAG{jangan_klik_link}'])
        ->assertRedirect(route('user.ctf.index'));

    $this->assertEquals(1, CtfSolve::where('user_id', $user->id)->count());
});
