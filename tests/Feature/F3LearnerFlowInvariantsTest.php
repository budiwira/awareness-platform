<?php

use App\Enums\UserRole;
use App\Models\ModuleAssignment;
use App\Models\Package;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use App\Models\UserModuleAccess;
use Inertia\Testing\AssertableInertia as Assert;

function buildAssignmentWithQuizzes()
{
    $tenant = Tenant::factory()->create();
    $package = Package::create([
        'name' => 'T-'.uniqid(),
        'slug' => 't-'.uniqid(),
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
        'title' => 'Module '.uniqid(),
        'content' => 'x',
        'content_html' => '<p>x</p>',
        'duration_minutes' => 10,
        'status' => 'published',
        'is_active' => true,
    ]);

    $pretest = Quiz::create([
        'training_module_id' => $module->id,
        'title' => 'Pretest',
        'passing_score' => 50,
        'purpose' => 'pretest',
    ]);
    $posttest = Quiz::create([
        'training_module_id' => $module->id,
        'title' => 'Posttest',
        'passing_score' => 50,
        'purpose' => 'posttest',
    ]);

    QuizQuestion::create([
        'quiz_id' => $pretest->id,
        'question' => 'Q?',
        'options' => ['A', 'B'],
        'correct_index' => 0,
    ]);
    QuizQuestion::create([
        'quiz_id' => $posttest->id,
        'question' => 'Q?',
        'options' => ['A', 'B'],
        'correct_index' => 0,
    ]);

    $module->update([
        'pretest_quiz_id' => $pretest->id,
        'posttest_quiz_id' => $posttest->id,
    ]);

    $assignment = ModuleAssignment::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
        'score' => 0,
    ]);

    return [$user, $module, $pretest, $posttest, $assignment];
}

test('invariant F3: pretest attempt TIDAK update assignment.score', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $questionId = $pretest->questions->first()->id;

    $attempt = QuizAttempt::create([
        'quiz_id' => $pretest->id,
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'status' => 'in_progress',
        'started_at' => now(),
        'question_order' => [$questionId],
        'option_orders' => [$questionId => [0, 1]],
    ]);

    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attempt), [
            'answers' => [$questionId => 0],
        ])
        ->assertOk();

    $assignment->refresh();
    expect($assignment->score)->toBe(0)
        ->and($assignment->pretest_score)->toBe(100)
        ->and($assignment->status)->toBe('assigned');
});

test('invariant F3: pretest attempt TIDAK mark completed', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $questionId = $pretest->questions->first()->id;

    $attempt = QuizAttempt::create([
        'quiz_id' => $pretest->id,
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'status' => 'in_progress',
        'started_at' => now(),
        'question_order' => [$questionId],
        'option_orders' => [$questionId => [0, 1]],
    ]);

    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attempt), [
            'answers' => [$questionId => 0],
        ]);

    $assignment->refresh();
    expect($assignment->status)->toBe('assigned')
        ->and($assignment->completed_at)->toBeNull()
        ->and($assignment->pretest_completed_at)->not->toBeNull();
});

test('invariant F3: posttest attempt update assignment.score', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $questionId = $posttest->questions->first()->id;

    $attempt = QuizAttempt::create([
        'quiz_id' => $posttest->id,
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'status' => 'in_progress',
        'started_at' => now(),
        'question_order' => [$questionId],
        'option_orders' => [$questionId => [0, 1]],
    ]);

    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attempt), [
            'answers' => [$questionId => 0],
        ]);

    $assignment->refresh();
    expect($assignment->score)->toBe(100);
});

test('invariant F3: posttest passed mark completed', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $questionId = $posttest->questions->first()->id;

    $attempt = QuizAttempt::create([
        'quiz_id' => $posttest->id,
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'status' => 'in_progress',
        'started_at' => now(),
        'question_order' => [$questionId],
        'option_orders' => [$questionId => [0, 1]],
    ]);

    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $attempt), [
            'answers' => [$questionId => 0],
        ]);

    $assignment->refresh();
    expect($assignment->status)->toBe('completed')
        ->and($assignment->completed_at)->not->toBeNull();
});

test('invariant F3: learning gain computed correctly', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $preQuestionId = $pretest->questions->first()->id;
    $postQuestionId = $posttest->questions->first()->id;

    // Submit pretest: score 0 (salah)
    $pre = QuizAttempt::create([
        'quiz_id' => $pretest->id,
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'status' => 'in_progress',
        'started_at' => now(),
        'question_order' => [$preQuestionId],
        'option_orders' => [$preQuestionId => [0, 1]],
    ]);
    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $pre), [
            'answers' => [$preQuestionId => 1], // salah
        ]);

    // Submit posttest: score 100 (benar)
    $post = QuizAttempt::create([
        'quiz_id' => $posttest->id,
        'user_id' => $user->id,
        'tenant_id' => $user->tenant_id,
        'status' => 'in_progress',
        'started_at' => now(),
        'question_order' => [$postQuestionId],
        'option_orders' => [$postQuestionId => [0, 1]],
    ]);
    $this->actingAs($user)
        ->postJson(route('user.training.quiz.submit', $post), [
            'answers' => [$postQuestionId => 0], // benar
        ]);

    $assignment->refresh();
    $learningGain = ($assignment->score ?? 0) - ($assignment->pretest_score ?? 0);
    expect($assignment->pretest_score)->toBe(0)
        ->and($assignment->score)->toBe(100)
        ->and($learningGain)->toBe(100);
});
test('invariant F3: learner dapat membuka halaman quiz pretest via purpose', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();

    $this->actingAs($user)
        ->get(route('user.training.quiz', ['assignment' => $assignment->id, 'purpose' => 'pretest']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/MyTraining/Quiz')
            ->where('quiz.title', 'Pretest')
            ->where('quiz.purpose', 'pretest')
        );
});

test('invariant F3: start dengan purpose=pretest membuat attempt hanya di quiz pretest', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();

    $this->actingAs($user)
        ->postJson(route('user.training.quiz.start', ['assignment' => $assignment->id, 'purpose' => 'pretest']))
        ->assertOk();

    expect(QuizAttempt::where('quiz_id', $pretest->id)->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(QuizAttempt::where('quiz_id', $posttest->id)->where('user_id', $user->id)->exists())->toBeFalse();
});

test('invariant F3: posttest show and start require a submitted pretest, regardless of score', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    QuizAttempt::create([
        'quiz_id' => $pretest->id, 'user_id' => $user->id, 'tenant_id' => $user->tenant_id,
        'status' => 'submitted', 'started_at' => now(), 'submitted_at' => now(), 'score' => 0, 'passed' => false,
    ]);

    $this->actingAs($user)->get(route('user.training.quiz', ['assignment' => $assignment->id, 'purpose' => 'posttest']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('User/MyTraining/Quiz')->where('quiz.id', $posttest->id)->where('quiz.purpose', 'posttest'));
    $this->postJson(route('user.training.quiz.start', ['assignment' => $assignment->id, 'purpose' => 'posttest']), ['quiz_id' => $posttest->id])
        ->assertOk()->assertJsonPath('quiz.id', $posttest->id);
    expect(QuizAttempt::where('status', 'in_progress')->where('user_id', $user->id)->pluck('quiz_id')->all())->toBe([$posttest->id]);
});

test('invariant F3: missing or unfinished pretest blocks posttest show and start', function ($status) {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    if ($status) {
        QuizAttempt::create([
            'quiz_id' => $pretest->id, 'user_id' => $user->id, 'tenant_id' => $user->tenant_id,
            'status' => $status, 'started_at' => now(),
        ]);
    }
    $params = ['assignment' => $assignment->id, 'purpose' => 'posttest'];
    $this->actingAs($user)->get(route('user.training.quiz', $params))->assertForbidden();
    $this->postJson(route('user.training.quiz.start', $params))->assertForbidden();
    expect(QuizAttempt::where('quiz_id', $posttest->id)->exists())->toBeFalse();
})->with([null, 'in_progress', 'expired']);

test('invariant F3: default quiz follows module flow and a stale quiz id is rejected', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $this->actingAs($user)->get(route('user.training.quiz', $assignment))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('User/MyTraining/Quiz')->where('quiz.id', $pretest->id)->where('quiz.purpose', 'pretest'));
    $this->postJson(route('user.training.quiz.start', ['assignment' => $assignment->id, 'purpose' => 'pretest']), ['quiz_id' => $posttest->id])
        ->assertUnprocessable()->assertJsonValidationErrors('quiz_id');
    expect(QuizAttempt::where('user_id', $user->id)->exists())->toBeFalse();
});

test('invariant F3: required posttest cannot be bypassed by manual completion', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $this->actingAs($user)->patchJson(route('user.training.complete', $assignment))->assertForbidden();
    expect($assignment->fresh()->status)->toBe('assigned')->and($assignment->fresh()->completed_at)->toBeNull();
});

test('invariant F3: authorized manual completion without posttest respects pretest', function ($hasPretest) {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $module->update(['posttest_quiz_id' => null, 'pretest_quiz_id' => $hasPretest ? $pretest->id : null]);
    if ($hasPretest) {
        $this->actingAs($user)->patchJson(route('user.training.complete', $assignment))->assertForbidden();
        QuizAttempt::create([
            'quiz_id' => $pretest->id, 'user_id' => $user->id, 'tenant_id' => $user->tenant_id,
            'status' => 'submitted', 'started_at' => now(), 'submitted_at' => now(), 'score' => 0, 'passed' => false,
        ]);
    }
    $this->actingAs($user)->patch(route('user.training.complete', $assignment))->assertRedirect(route('user.training.index'));
    expect($assignment->fresh()->status)->toBe('completed');
    $this->assertDatabaseHas('audit_logs', ['action' => 'training.completed']);
})->with([false, true]);

test('invariant F3: manual completion requires entitlement and user access', function ($revoked) {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $module->update(['pretest_quiz_id' => null, 'posttest_quiz_id' => null]);
    if ($revoked) {
        UserModuleAccess::create([
            'user_id' => $user->id, 'tenant_id' => $user->tenant_id,
            'training_module_id' => $module->id, 'is_allowed' => false,
        ]);
    } else {
        Subscription::where('tenant_id', $user->tenant_id)->update(['status' => 'cancelled']);
    }
    $this->actingAs($user)->patchJson(route('user.training.complete', $assignment))->assertForbidden();
    expect($assignment->fresh()->status)->toBe('assigned');
})->with([false, true]);

test('invariant F3: other users and tenants cannot open, start or complete an assignment', function ($crossTenant) {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $other = User::factory()->create([
        'tenant_id' => $crossTenant ? Tenant::factory()->create()->id : $user->tenant_id,
        'role' => UserRole::User,
    ]);
    $params = ['assignment' => $assignment->id, 'purpose' => 'pretest'];
    $this->actingAs($other)->get(route('user.training.quiz', $params))->assertForbidden();
    $this->postJson(route('user.training.quiz.start', $params))->assertForbidden();
    $this->patchJson(route('user.training.complete', $assignment))->assertForbidden();
    expect(QuizAttempt::where('user_id', $other->id)->exists())->toBeFalse();
})->with([false, true]);

test('invariant F3: tenant admin cannot use learner quiz or completion routes', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $user->update(['role' => UserRole::TenantAdmin]);
    $params = ['assignment' => $assignment->id, 'purpose' => 'pretest'];
    $this->actingAs($user)->get(route('user.training.quiz', $params))->assertForbidden();
    $this->postJson(route('user.training.quiz.start', $params))->assertForbidden();
    $this->patchJson(route('user.training.complete', $assignment))->assertForbidden();
});

test('invariant F3: posttest without pretest opens and starts the configured quiz', function () {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    $module->update(['pretest_quiz_id' => null]);
    $params = ['assignment' => $assignment->id, 'purpose' => 'posttest'];
    $this->actingAs($user)->get(route('user.training.quiz', $params))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('User/MyTraining/Quiz')->where('quiz.id', $posttest->id)->where('quiz.purpose', 'posttest'));
    $this->postJson(route('user.training.quiz.start', $params), ['quiz_id' => $posttest->id])
        ->assertOk()->assertJsonPath('quiz.id', $posttest->id);
});

test('invariant F3: misconfigured quiz links fail closed', function ($mismatch) {
    [$user, $module, $pretest, $posttest, $assignment] = buildAssignmentWithQuizzes();
    if ($mismatch === 'module') {
        $otherModule = TrainingModule::create(['title' => 'Other', 'content' => 'x', 'duration_minutes' => 10]);
        $pretest->update(['training_module_id' => $otherModule->id]);
    } elseif ($mismatch === 'purpose') {
        $module->update(['pretest_quiz_id' => $posttest->id, 'posttest_quiz_id' => null]);
    } else {
        $module->update(['posttest_quiz_id' => $pretest->id]);
    }
    $params = ['assignment' => $assignment->id, 'purpose' => $mismatch === 'shared' ? 'posttest' : 'pretest'];
    $this->actingAs($user)->getJson(route('user.training.quiz', $params))->assertUnprocessable();
    $this->postJson(route('user.training.quiz.start', $params))->assertUnprocessable();
    expect(QuizAttempt::where('user_id', $user->id)->exists())->toBeFalse();
})->with(['module', 'purpose', 'shared']);
