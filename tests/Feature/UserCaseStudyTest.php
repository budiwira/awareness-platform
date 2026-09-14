<?php

use App\Models\CaseParticipation;
use App\Models\CaseScene;
use App\Models\CaseStudy;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserFeatureAccess;
use Inertia\Testing\AssertableInertia as Assert;

function makeCaseFixture(): array
{
    $tenant = Tenant::factory()->create();
    $Package = Package::create(['name' => 'Case-'.uniqid(), 'slug' => 'case-'.uniqid(), 'price_monthly' => 100, 'max_users' => 100, 'features' => ['case_studies'], 'includes_all_modules' => false, 'is_active' => true]);
    Subscription::create(['tenant_id' => $tenant->id, 'package_id' => $Package->id, 'status' => 'active', 'started_at' => now()]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $case = CaseStudy::create(['title' => 'Ransomware', 'difficulty' => 'beginner', 'duration_minutes' => 15, 'is_active' => true, 'status' => 'published']);

    $scene = CaseScene::create([
        'case_study_id' => $case->id,
        'order' => 1,
        'situation' => 'Layar karyawan terkunci, muncul permintaan tebusan.',
        'options' => [
            ['text' => 'Isolasi mesin & lapor tim IT', 'quality' => 'best', 'feedback' => 'Tepat: isolasi mencegah penyebaran.'],
            ['text' => 'Bayar tebusan segera', 'quality' => 'poor', 'feedback' => 'Membayar tidak menjamin data kembali.'],
        ],
    ]);

    return [$user, $case, $scene];
}

test('run payload never contains quality or feedback', function () {
    [$user, $case, $scene] = makeCaseFixture();

    $this->actingAs($user)->post(route('user.cases.start', $case));

    $participation = CaseParticipation::first();

    $this->actingAs($user)
        ->get(route('user.cases.run', $participation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/Cases/Run')
            ->has('scenes', 1, fn (Assert $s) => $s
                ->has('options', 2, fn (Assert $o) => $o->missing('quality')->missing('feedback')->etc())
                ->etc()
            )
        );
});

test('best decisions score 100 and complete participation', function () {
    [$user, $case, $scene] = makeCaseFixture();

    $this->actingAs($user)->post(route('user.cases.start', $case));
    $participation = CaseParticipation::first();

    $this->actingAs($user)
        ->post(route('user.cases.submit', $participation), [
            'answers' => [$scene->id => 0],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('case_participations', ['id' => $participation->id, 'score' => 100, 'status' => 'completed']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'case.completed']);
});

test('poor decisions score 0', function () {
    [$user, $case, $scene] = makeCaseFixture();

    $this->actingAs($user)->post(route('user.cases.start', $case));
    $participation = CaseParticipation::first();

    $this->actingAs($user)
        ->post(route('user.cases.submit', $participation), [
            'answers' => [$scene->id => 1],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('case_participations', ['id' => $participation->id, 'score' => 0]);
});

test('unanswered scenes rejected', function () {
    [$user, $case, $scene] = makeCaseFixture();

    $this->actingAs($user)->post(route('user.cases.start', $case));
    $participation = CaseParticipation::first();

    $this->actingAs($user)
        ->post(route('user.cases.submit', $participation), ['answers' => []])
        ->assertSessionHasErrors('answers');
});

test('user cannot run another user participation', function () {
    [$user, $case, $scene] = makeCaseFixture();
    $other = User::factory()->create(['tenant_id' => $user->tenant_id]);

    $participation = CaseParticipation::create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'case_study_id' => $case->id,
        'status' => 'in_progress',
    ]);

    $this->actingAs($other)->get(route('user.cases.run', $participation))->assertForbidden();
});

test('learner cannot view case study result or best answers before completion', function () {
    [$user, $case, $scene] = makeCaseFixture();
    $this->actingAs($user)->post(route('user.cases.start', $case))->assertRedirect();
    $participation = CaseParticipation::where('user_id', $user->id)->firstOrFail();

    $this->getJson(route('user.cases.result', $participation))
        ->assertForbidden()->assertJsonMissingPath('breakdown')
        ->assertDontSee($scene->options[0]['text'], false)
        ->assertDontSee($scene->options[0]['feedback'], false);
    expect($participation->fresh()->status)->toBe('in_progress');
});

test('learner can view own completed case study result with existing feedback', function () {
    [$user, $case, $scene] = makeCaseFixture();
    $this->actingAs($user)->post(route('user.cases.start', $case))->assertRedirect();
    $participation = CaseParticipation::where('user_id', $user->id)->firstOrFail();
    $this->post(route('user.cases.submit', $participation), ['answers' => [$scene->id => 0]])
        ->assertRedirect(route('user.cases.result', $participation));

    $this->get(route('user.cases.result', $participation))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('User/Cases/Result')
            ->where('case_title', $case->title)->where('score', 100)
            ->has('breakdown', 1)->where('breakdown.0.best', $scene->options[0]['text'])
            ->where('breakdown.0.chosen.quality', 'best')
            ->where('breakdown.0.chosen.feedback', $scene->options[0]['feedback']));
});

test('other learner cannot view completed case study result', function ($crossTenant) {
    [$owner, $case, $scene] = makeCaseFixture();
    // Both callers have entitlement, so rejection must not depend on a missing package.
    $other = $crossTenant ? makeCaseFixture()[0] : User::factory()->create(['tenant_id' => $owner->tenant_id]);
    $participation = CaseParticipation::create([
        'user_id' => $owner->id, 'tenant_id' => $owner->tenant_id, 'case_study_id' => $case->id,
        'status' => 'completed', 'score' => 100, 'decisions' => [$scene->id => 0], 'completed_at' => now(),
    ]);

    $this->actingAs($other)->getJson(route('user.cases.result', $participation))
        ->assertForbidden()->assertJsonMissingPath('breakdown')
        ->assertDontSee($scene->options[0]['text'], false);
})->with(['same tenant' => false, 'wrong tenant' => true]);

test('unauthorized role cannot view case study result', function ($role) {
    [$user, $case, $scene] = makeCaseFixture();
    $participation = CaseParticipation::create([
        'user_id' => $user->id, 'tenant_id' => $user->tenant_id, 'case_study_id' => $case->id,
        'status' => 'completed', 'score' => 100, 'decisions' => [$scene->id => 0], 'completed_at' => now(),
    ]);
    $user->update(['role' => $role, 'tenant_id' => $role === 'super_admin' ? null : $user->tenant_id]);

    $this->actingAs($user)->getJson(route('user.cases.result', $participation))
        ->assertForbidden()->assertJsonMissingPath('breakdown');
})->with(['tenant_admin', 'super_admin']);

test('completed case study result still requires entitlement and user access', function ($revoked) {
    [$user, $case, $scene] = makeCaseFixture();
    $participation = CaseParticipation::create([
        'user_id' => $user->id, 'tenant_id' => $user->tenant_id, 'case_study_id' => $case->id,
        'status' => 'completed', 'score' => 100, 'decisions' => [$scene->id => 0], 'completed_at' => now(),
    ]);
    if ($revoked) {
        UserFeatureAccess::create([
            'user_id' => $user->id, 'tenant_id' => $user->tenant_id,
            'feature_key' => 'case_studies', 'is_allowed' => false,
        ]);
    } else {
        Subscription::where('tenant_id', $user->tenant_id)->update(['status' => 'cancelled']);
    }

    $this->actingAs($user)->getJson(route('user.cases.result', $participation))
        ->assertForbidden()->assertJsonMissingPath('breakdown');
})->with(['no entitlement' => false, 'access revoked' => true]);
