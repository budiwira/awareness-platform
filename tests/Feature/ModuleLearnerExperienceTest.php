<?php

use App\Models\ModuleAssignment;
use App\Models\Package;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use App\Services\AssessmentLifecycle;
use Inertia\Testing\AssertableInertia as Assert;

function learnerExperienceFixture(bool $withPretest = true, bool $withPosttest = true): array
{
    $tenant = Tenant::factory()->create();
    $package = Package::create([
        'name' => 'Learner Experience '.uniqid(),
        'slug' => 'learner-experience-'.uniqid(),
        'price_monthly' => 100,
        'max_users' => 20,
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
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $module = TrainingModule::create([
        'title' => 'Secure Learning Journey',
        'description' => 'Assignment scoped learning',
        'content' => 'Trusted learning material',
        'content_html' => '<p>Trusted learning material</p>',
        'duration_minutes' => 25,
        'status' => 'published',
        'is_active' => true,
    ]);

    $pretest = $withPretest ? Quiz::create([
        'training_module_id' => $module->id,
        'purpose' => 'pretest',
        'title' => 'Baseline',
        'passing_score' => 1,
        'duration_minutes' => 10,
        'is_active' => true,
    ]) : null;
    $posttest = $withPosttest ? Quiz::create([
        'training_module_id' => $module->id,
        'purpose' => 'posttest',
        'title' => 'Final Assessment',
        'passing_score' => 70,
        'duration_minutes' => 10,
        'is_active' => true,
    ]) : null;

    foreach (array_filter([$pretest, $posttest]) as $quiz) {
        QuizQuestion::create([
            'quiz_id' => $quiz->id,
            'question' => 'Secure behavior?',
            'options' => ['Yes', 'No'],
            'correct_index' => 0,
            'explanation' => 'Yes is correct.',
        ]);
    }
    $module->update([
        'pretest_quiz_id' => $pretest?->id,
        'posttest_quiz_id' => $posttest?->id,
    ]);
    $assignment = ModuleAssignment::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'training_module_id' => $module->id,
        'status' => 'assigned',
        'deadline_at' => now()->addDay(),
    ]);

    return compact('tenant', 'user', 'module', 'pretest', 'posttest', 'assignment');
}

function terminalLearnerAttempt(ModuleAssignment $assignment, Quiz $quiz, array $attributes = []): QuizAttempt
{
    return QuizAttempt::create([
        'module_assignment_id' => $assignment->id,
        'quiz_id' => $quiz->id,
        'assessment_purpose' => $quiz->purpose,
        'user_id' => $assignment->user_id,
        'tenant_id' => $assignment->tenant_id,
        'status' => 'submitted',
        'started_at' => now()->subMinutes(5),
        'submitted_at' => now(),
        'score' => 50,
        'passed' => $quiz->purpose === 'posttest' ? false : null,
        'answers' => [],
        'question_order' => $quiz->questions()->pluck('id')->all(),
        'option_orders' => $quiz->questions()->pluck('id')->mapWithKeys(fn ($id) => [$id => [0, 1]])->all(),
        ...$attributes,
    ]);
}

test('module room hides content before baseline and projects assignment scoped journey data', function () {
    $fixture = learnerExperienceFixture();

    $response = $this->actingAs($fixture['user'])->get(route('user.training.show', $fixture['assignment']));
    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('User/MyTraining/Show')
            ->where('assignment.id', $fixture['assignment']->id)
            ->where('lifecycle.stage', 'pretest_required')
            ->where('module.content_html', null)
            ->missing('assignment.module_snapshot')
            ->missing('assignment.module')
            ->missing('module.module_snapshot')
            ->missing('module.quiz')
            ->missing('pretestQuiz.passing_score')
            ->where('lifecycle.can_start_pretest', true));
    $response->assertDontSee('Trusted learning material', false);

    $this->get(route('user.training.quiz', ['assignment' => $fixture['assignment'], 'purpose' => 'pretest']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('quiz.purpose', 'pretest')
            ->where('quiz.passing_score', null)
            ->where('attemptContext.retry_allowed', false));

    $this->postJson(route('user.training.quiz.start', ['assignment' => $fixture['assignment'], 'purpose' => 'pretest']))
        ->assertOk()
        ->assertJsonPath('quiz.passing_score', null)
        ->assertJsonMissingPath('questions.0.correct_index')
        ->assertJsonMissingPath('questions.0.explanation');
});

test('terminal pretest result is baseline only and review stays locked while posttest is pending', function (string $status) {
    $fixture = learnerExperienceFixture();
    $attempt = terminalLearnerAttempt($fixture['assignment'], $fixture['pretest'], [
        'status' => $status,
        'score' => 55,
        'passed' => null,
    ]);
    $fixture['assignment']->update(['pretest_score' => 55, 'pretest_completed_at' => now()]);

    $this->actingAs($fixture['user'])->get(route('user.quiz.result', $attempt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attempt.purpose', 'pretest')
            ->where('attempt.passed', null)
            ->where('attempt.passing_score', null)
            ->where('attemptContext.retry_allowed', false)
            ->where('actions.review_allowed', false)
            ->where('assignment.learning_gain_available', false)
            ->missing('attempt.answers'));
    $this->get(route('user.training.quiz.review', $attempt))->assertForbidden();
    $this->get(route('user.training.show', $fixture['assignment']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('lifecycle.stage', 'material')
            ->where('module.content_html', '<p>Trusted learning material</p>'));
})->with(['submitted', 'expired']);

test('content progress persists and no posttest module completes idempotently', function () {
    $fixture = learnerExperienceFixture(withPretest: false, withPosttest: false);

    $this->actingAs($fixture['user'])
        ->patch(route('user.training.content.start', $fixture['assignment']))
        ->assertRedirect();
    expect($fixture['assignment']->fresh()->content_started_at)->not->toBeNull();

    $this->patch(route('user.training.content.complete', $fixture['assignment']))->assertRedirect();
    $completedAt = $fixture['assignment']->fresh()->completed_at;
    expect($fixture['assignment']->fresh()->status)->toBe('completed')
        ->and($fixture['assignment']->fresh()->content_completed_at)->not->toBeNull();
    $this->patch(route('user.training.content.complete', $fixture['assignment']))->assertRedirect();
    expect($fixture['assignment']->fresh()->completed_at->timestamp)->toBe($completedAt->timestamp);
});

test('invalid assessment binding fails closed for module room and direct content mutations', function () {
    $fixture = learnerExperienceFixture();
    $foreignModule = TrainingModule::create([
        'title' => 'Foreign Module',
        'content' => 'Foreign content',
        'status' => 'published',
        'is_active' => true,
    ]);
    $invalidPretest = Quiz::create([
        'training_module_id' => $foreignModule->id,
        'purpose' => 'pretest',
        'title' => 'Invalid binding',
        'passing_score' => 1,
        'is_active' => true,
    ]);
    $fixture['assignment']->update(['pretest_quiz_id' => $invalidPretest->id]);

    $this->actingAs($fixture['user'])->get(route('user.training.show', $fixture['assignment']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('lifecycle.stage', 'configuration_unavailable')
            ->where('module.content_html', null)
            ->where('lifecycle.can_start_content', false)
            ->where('lifecycle.can_complete_content', false));

    $this->patch(route('user.training.content.start', $fixture['assignment']))->assertUnprocessable();
    $this->patch(route('user.training.content.complete', $fixture['assignment']))->assertUnprocessable();
    expect($fixture['assignment']->fresh()->content_started_at)->toBeNull()
        ->and($fixture['assignment']->fresh()->content_completed_at)->toBeNull();
});

test('failed posttest result separates attempt score best score quota cooldown and review eligibility', function () {
    $fixture = learnerExperienceFixture(withPretest: false);
    $fixture['assignment']->update([
        'status' => 'in_progress',
        'content_started_at' => now(),
        'content_completed_at' => now(),
        'score' => 65,
    ]);
    $attempt = terminalLearnerAttempt($fixture['assignment'], $fixture['posttest'], [
        'score' => 62,
        'passed' => false,
    ]);

    $this->actingAs($fixture['user'])->get(route('user.quiz.result', $attempt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attempt.score', 62)
            ->where('attempt.number', 1)
            ->where('attempt.passed', false)
            ->where('assignment.best_posttest_score', 65)
            ->where('attemptContext.attempts_used', 1)
            ->where('attemptContext.attempts_remaining', 2)
            ->where('attemptContext.retry_allowed', false)
            ->where('actions.review_allowed', false)
            ->where('assignment.learning_gain_available', false));
    $this->get(route('user.training.quiz.review', $attempt))->assertForbidden();
    $this->postJson(route('user.training.quiz.start', ['assignment' => $fixture['assignment'], 'purpose' => 'posttest']))
        ->assertUnprocessable()
        ->assertJsonPath('cooldown_until', fn ($value) => is_string($value));
});

test('expired-only posttest does not turn default zero into a best score or learning gain', function () {
    $fixture = learnerExperienceFixture();
    $fixture['assignment']->update([
        'status' => 'in_progress',
        'pretest_score' => 40,
        'pretest_completed_at' => now()->subHours(3),
        'content_started_at' => now()->subHours(2),
        'content_completed_at' => now()->subHour(),
    ]);
    $attempt = terminalLearnerAttempt($fixture['assignment'], $fixture['posttest'], [
        'status' => 'expired',
        'score' => 0,
        'passed' => false,
    ]);

    $this->actingAs($fixture['user'])->get(route('user.quiz.result', $attempt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attempt.score', 0)
            ->where('attempt.expired', true)
            ->where('assignment.best_posttest_score', null)
            ->where('assignment.learning_gain', null)
            ->where('assignment.learning_gain_available', false));
});

test('a legitimate submitted zero remains a valid best score and gain input', function () {
    $fixture = learnerExperienceFixture();
    $fixture['assignment']->update([
        'status' => 'in_progress',
        'pretest_score' => 0,
        'pretest_completed_at' => now()->subHours(3),
        'content_started_at' => now()->subHours(2),
        'content_completed_at' => now()->subHour(),
        'score' => 0,
    ]);
    $attempt = terminalLearnerAttempt($fixture['assignment'], $fixture['posttest'], [
        'score' => 0,
        'passed' => false,
    ]);

    $this->actingAs($fixture['user'])->get(route('user.quiz.result', $attempt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignment.best_posttest_score', 0)
            ->where('assignment.learning_gain', 0)
            ->where('assignment.learning_gain_available', true));
});

test('posttest review unlocks after pass or exhaustion and then unlocks pretest review', function (string $terminal) {
    $fixture = learnerExperienceFixture();
    $pretestAttempt = terminalLearnerAttempt($fixture['assignment'], $fixture['pretest'], [
        'score' => 40,
        'passed' => null,
        'submitted_at' => now()->subDay(),
    ]);
    $fixture['assignment']->update([
        'pretest_score' => 40,
        'pretest_completed_at' => now()->subDay(),
        'content_started_at' => now()->subHours(3),
        'content_completed_at' => now()->subHours(2),
        'status' => $terminal === 'passed' ? 'completed' : 'in_progress',
        'score' => $terminal === 'passed' ? 80 : 60,
        'completed_at' => $terminal === 'passed' ? now() : null,
    ]);

    $posttestAttempts = [];
    $count = $terminal === 'passed' ? 1 : AssessmentLifecycle::POSTTEST_MAX_ATTEMPTS;
    foreach (range(1, $count) as $number) {
        $posttestAttempts[] = terminalLearnerAttempt($fixture['assignment'], $fixture['posttest'], [
            'score' => $terminal === 'passed' ? 80 : 60,
            'passed' => $terminal === 'passed',
            'submitted_at' => now()->subMinutes($count - $number),
        ]);
    }
    $posttestAttempt = end($posttestAttempts);

    $this->actingAs($fixture['user'])->get(route('user.training.quiz.review', $posttestAttempt))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('questions.0.correct_index')
        ->has('questions.0.explanation'));
    $this->get(route('user.quiz.result', $posttestAttempt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attemptContext.attempts_remaining', 0)
            ->where('actions.review_allowed', true));
    $this->get(route('user.training.quiz.review', $pretestAttempt))->assertOk();
    $this->get(route('user.quiz.result', $pretestAttempt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attempt.purpose', 'pretest')
            ->where('assignment.best_posttest_score', null)
            ->where('assignment.learning_gain', null)
            ->where('assignment.learning_gain_available', false)
            ->where('attemptContext.best_valid_posttest_score', null));
})->with(['passed', 'exhausted']);

test('result reports positive negative and zero learning gain in percentage points', function (int $baseline, int $best, int $gain) {
    $fixture = learnerExperienceFixture();
    $fixture['assignment']->update([
        'status' => 'completed',
        'pretest_score' => $baseline,
        'pretest_completed_at' => now()->subDay(),
        'content_started_at' => now()->subHours(2),
        'content_completed_at' => now()->subHour(),
        'score' => $best,
        'completed_at' => now(),
    ]);
    $attempt = terminalLearnerAttempt($fixture['assignment'], $fixture['posttest'], [
        'score' => $best,
        'passed' => true,
    ]);

    $this->actingAs($fixture['user'])->get(route('user.quiz.result', $attempt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignment.baseline_score', $baseline)
            ->where('assignment.best_posttest_score', $best)
            ->where('assignment.learning_gain', $gain)
            ->where('assignment.learning_gain_available', true));
})->with([
    'positive' => [55, 80, 25],
    'negative' => [80, 70, -10],
    'zero' => [70, 70, 0],
]);

test('multiple assignment cycles keep fresh lifecycle quota and historical result identity', function () {
    $fixture = learnerExperienceFixture();
    $first = $fixture['assignment'];
    $first->update([
        'status' => 'completed',
        'pretest_score' => 50,
        'pretest_completed_at' => now()->subDays(2),
        'content_started_at' => now()->subDays(2),
        'content_completed_at' => now()->subDays(2),
        'score' => 80,
        'completed_at' => now()->subDay(),
    ]);
    $oldAttempts = [];
    foreach ([40, 50, 80] as $index => $score) {
        $oldAttempts[] = terminalLearnerAttempt($first, $fixture['posttest'], [
            'score' => $score,
            'passed' => $index === 2,
            'submitted_at' => now()->subDays(3 - $index),
        ]);
    }
    $oldAttempt = end($oldAttempts);
    $second = ModuleAssignment::create([
        'tenant_id' => $fixture['tenant']->id,
        'user_id' => $fixture['user']->id,
        'training_module_id' => $fixture['module']->id,
        'status' => 'assigned',
    ]);

    $this->actingAs($fixture['user'])->get(route('user.training.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assignments', 2)
            ->where('assignments.0.id', $second->id)
            ->where('assignments.0.stage', 'pretest_required')
            ->where('assignments.1.id', $first->id)
            ->where('assignments.1.stage', 'completed'));
    $this->get(route('user.quiz.result', $oldAttempt))
        ->assertInertia(fn (Assert $page) => $page->where('assignment.id', $first->id));
    expect(app(AssessmentLifecycle::class)->state($second)['posttest_attempts_used'])->toBe(0)
        ->and($second->pretest_score)->toBeNull()
        ->and($first->fresh()->score)->toBe(80);

    terminalLearnerAttempt($second, $fixture['pretest'], ['score' => 30, 'passed' => null]);
    $second->update([
        'status' => 'in_progress',
        'pretest_score' => 30,
        'pretest_completed_at' => now(),
        'content_started_at' => now(),
        'content_completed_at' => now(),
        'score' => 55,
    ]);
    $secondPosttest = terminalLearnerAttempt($second, $fixture['posttest'], ['score' => 55, 'passed' => false]);

    $this->get(route('user.quiz.result', $secondPosttest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignment.id', $second->id)
            ->where('attempt.number', 1)
            ->where('attemptContext.attempts_used', 1)
            ->where('attemptContext.attempts_remaining', 2));
    expect($first->fresh()->score)->toBe(80);
});

test('result and review direct urls deny cross user and cross tenant access', function (bool $crossTenant) {
    $fixture = learnerExperienceFixture(withPretest: false);
    $fixture['assignment']->update([
        'status' => 'completed',
        'content_started_at' => now()->subHour(),
        'content_completed_at' => now()->subMinutes(30),
        'score' => 100,
        'completed_at' => now(),
    ]);
    $attempt = terminalLearnerAttempt($fixture['assignment'], $fixture['posttest'], [
        'score' => 100,
        'passed' => true,
    ]);
    $intruder = User::factory()->create([
        'tenant_id' => $crossTenant ? Tenant::factory()->create()->id : $fixture['tenant']->id,
    ]);

    $this->actingAs($intruder)->get(route('user.quiz.result', $attempt))->assertForbidden();
    $this->get(route('user.training.quiz.review', $attempt))->assertForbidden();
})->with([
    'same tenant other learner' => false,
    'different tenant learner' => true,
]);
