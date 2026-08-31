<?php

use App\Models\CaseParticipation;
use App\Models\CaseScene;
use App\Models\CaseStudy;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function makeCaseFixture(): array
{
    $tenant = Tenant::factory()->create();
    $plan = \App\Models\Plan::create(['name' => 'Case-'.uniqid(), 'slug' => 'case-'.uniqid(), 'price_monthly' => 100, 'max_users' => 100, 'features' => ['case_studies'], 'includes_all_modules' => false, 'is_active' => true]);
    \App\Models\Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => 'active', 'started_at' => now()]);
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