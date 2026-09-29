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
use App\Services\AssessmentAuthoringGuard;
use App\Services\AssessmentLifecycle;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

function moduleFinalFixture(bool $withPretest = true, bool $withPosttest = true): array
{
    $tenant = Tenant::factory()->create();
    $package = Package::create([
        'name' => 'Final '.uniqid(), 'slug' => 'final-'.uniqid(), 'price_monthly' => 100,
        'max_users' => 100, 'features' => ['training'], 'includes_all_modules' => true, 'is_active' => true,
    ]);
    Subscription::create([
        'tenant_id' => $tenant->id, 'package_id' => $package->id, 'status' => 'active', 'started_at' => now(),
    ]);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::TenantAdmin, 'is_active' => true]);
    $learner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::User, 'is_active' => true]);
    $module = TrainingModule::create([
        'title' => 'Snapshot title', 'description' => 'Snapshot description', 'content' => 'Trusted content',
        'content_html' => '<p>Trusted content</p>', 'duration_minutes' => 15, 'status' => 'published', 'is_active' => true,
    ]);

    $pretest = $withPretest ? Quiz::create([
        'training_module_id' => $module->id, 'title' => 'Pre A', 'passing_score' => 70, 'purpose' => 'pretest',
    ]) : null;
    $posttest = $withPosttest ? Quiz::create([
        'training_module_id' => $module->id, 'title' => 'Post A', 'passing_score' => 70, 'purpose' => 'posttest',
    ]) : null;

    foreach (array_filter([$pretest, $posttest]) as $quiz) {
        QuizQuestion::create([
            'quiz_id' => $quiz->id, 'question' => 'Question?', 'options' => ['Correct', 'Wrong'], 'correct_index' => 0,
        ]);
    }
    $module->update(['pretest_quiz_id' => $pretest?->id, 'posttest_quiz_id' => $posttest?->id]);

    return compact('tenant', 'admin', 'learner', 'module', 'pretest', 'posttest');
}

test('tenant assignment snapshots trusted module bindings actor and deadline', function () {
    $fixture = moduleFinalFixture();
    $deadline = now()->addWeek()->startOfMinute();

    $this->actingAs($fixture['admin'])->post(route('tenant.assignments.store'), [
        'user_id' => $fixture['learner']->id,
        'training_module_id' => $fixture['module']->id,
        'deadline_at' => $deadline->toIso8601String(),
    ])->assertRedirect(route('tenant.assignments.index'));

    $assignment = ModuleAssignment::sole();
    expect($assignment->assigned_by)->toBe($fixture['admin']->id)
        ->and($assignment->assigned_at)->not->toBeNull()
        ->and($assignment->deadline_at->timestamp)->toBe($deadline->timestamp)
        ->and($assignment->pretest_quiz_id)->toBe($fixture['pretest']->id)
        ->and($assignment->posttest_quiz_id)->toBe($fixture['posttest']->id)
        ->and($assignment->module_snapshot)->toMatchArray([
            'schema_version' => 1, 'module_id' => $fixture['module']->id,
            'title' => 'Snapshot title', 'content_html' => '<p>Trusted content</p>',
        ]);
});

test('assignment request cannot forge lifecycle owned fields', function () {
    $fixture = moduleFinalFixture();

    $this->actingAs($fixture['admin'])->post(route('tenant.assignments.store'), [
        'user_id' => $fixture['learner']->id,
        'training_module_id' => $fixture['module']->id,
        'tenant_id' => Tenant::factory()->create()->id,
        'assigned_by' => $fixture['learner']->id,
        'status' => 'completed',
        'score' => 100,
        'pretest_completed_at' => now(),
        'content_started_at' => now(),
        'content_completed_at' => now(),
    ])->assertSessionHasErrors([
        'tenant_id', 'assigned_by', 'status', 'score',
        'pretest_completed_at', 'content_started_at', 'content_completed_at',
    ]);

    expect(ModuleAssignment::count())->toBe(0);
});

test('completed or cancelled history permits a future cycle but a second open assignment is rejected', function () {
    $fixture = moduleFinalFixture(false, false);
    $first = ModuleAssignment::create([
        'user_id' => $fixture['learner']->id, 'tenant_id' => $fixture['tenant']->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'completed', 'completed_at' => now(),
    ]);
    $second = ModuleAssignment::create([
        'user_id' => $fixture['learner']->id, 'tenant_id' => $fixture['tenant']->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'cancelled', 'cancelled_at' => now(),
    ]);
    $open = ModuleAssignment::create([
        'user_id' => $fixture['learner']->id, 'tenant_id' => $fixture['tenant']->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'assigned',
    ]);

    expect([$first->status, $second->status, $open->status])->toBe(['completed', 'cancelled', 'assigned']);
    expect(fn () => ModuleAssignment::create([
        'user_id' => $fixture['learner']->id, 'tenant_id' => $fixture['tenant']->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'in_progress',
    ]))->toThrow(QueryException::class);
});

test('historical assignment keeps assessment and content snapshots while new cycle receives replacements', function () {
    $fixture = moduleFinalFixture();
    $old = ModuleAssignment::create([
        'user_id' => $fixture['learner']->id, 'tenant_id' => $fixture['tenant']->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'completed', 'completed_at' => now(),
    ]);
    $preB = Quiz::create([
        'training_module_id' => $fixture['module']->id, 'title' => 'Pre B', 'passing_score' => 75, 'purpose' => 'pretest',
    ]);
    $postB = Quiz::create([
        'training_module_id' => $fixture['module']->id, 'title' => 'Post B', 'passing_score' => 75, 'purpose' => 'posttest',
    ]);
    $fixture['module']->update([
        'title' => 'Changed title', 'content_html' => '<p>Changed content</p>',
        'pretest_quiz_id' => $preB->id, 'posttest_quiz_id' => $postB->id,
    ]);
    $new = ModuleAssignment::create([
        'user_id' => $fixture['learner']->id, 'tenant_id' => $fixture['tenant']->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'assigned',
    ]);

    expect($old->fresh()->pretest_quiz_id)->toBe($fixture['pretest']->id)
        ->and($old->fresh()->posttest_quiz_id)->toBe($fixture['posttest']->id)
        ->and($old->fresh()->module_snapshot['title'])->toBe('Snapshot title')
        ->and($new->pretest_quiz_id)->toBe($preB->id)
        ->and($new->posttest_quiz_id)->toBe($postB->id)
        ->and($new->module_snapshot['title'])->toBe('Changed title')
        ->and(app(AssessmentAuthoringGuard::class)->isFrozen($fixture['pretest']))->toBeTrue();
});

test('required pretest content is omitted until a terminal baseline exists', function () {
    $fixture = moduleFinalFixture(true, false);
    $assignment = ModuleAssignment::create([
        'user_id' => $fixture['learner']->id, 'tenant_id' => $fixture['tenant']->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'assigned',
    ]);

    $this->actingAs($fixture['learner'])->get(route('user.training.show', $assignment))
        ->assertInertia(fn (Assert $page) => $page
            ->where('module.content_html', null)
            ->missing('assignment.module_snapshot')
            ->missing('assignment.module')
            ->missing('assignment.pretest_quiz')
            ->missing('assignment.posttest_quiz'));

    QuizAttempt::create([
        'module_assignment_id' => $assignment->id, 'assessment_purpose' => 'pretest',
        'quiz_id' => $fixture['pretest']->id, 'user_id' => $fixture['learner']->id,
        'tenant_id' => $fixture['tenant']->id, 'status' => 'expired', 'started_at' => now()->subMinute(),
        'submitted_at' => now(), 'score' => 0, 'passed' => null,
    ]);
    $assignment->update(['pretest_score' => 0, 'pretest_completed_at' => now()]);

    $this->get(route('user.training.show', $assignment))
        ->assertInertia(fn (Assert $page) => $page->where('module.content_html', '<p>Trusted content</p>'));
});

test('content completion is owner scoped and completes only modules without posttest', function () {
    $fixture = moduleFinalFixture(false, false);
    $assignment = ModuleAssignment::create([
        'user_id' => $fixture['learner']->id, 'tenant_id' => $fixture['tenant']->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'assigned',
    ]);
    $other = User::factory()->create(['tenant_id' => $fixture['tenant']->id, 'role' => UserRole::User]);

    $this->actingAs($other)->patchJson(route('user.training.content.complete', $assignment))->assertForbidden();
    $this->actingAs($fixture['learner'])->patch(route('user.training.content.complete', $assignment))->assertRedirect();

    expect($assignment->fresh()->content_started_at)->not->toBeNull()
        ->and($assignment->fresh()->content_completed_at)->not->toBeNull()
        ->and($assignment->fresh()->status)->toBe('completed')
        ->and($assignment->fresh()->completed_at)->not->toBeNull();

    $completedAt = $assignment->fresh()->content_completed_at;
    $this->patch(route('user.training.content.complete', $assignment))->assertRedirect();
    expect($assignment->fresh()->content_completed_at->timestamp)->toBe($completedAt->timestamp);
});

test('posttest requires content completion and expired posttest cannot pass or update best score', function () {
    $fixture = moduleFinalFixture(false, true);
    $assignment = ModuleAssignment::create([
        'user_id' => $fixture['learner']->id, 'tenant_id' => $fixture['tenant']->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'assigned',
    ]);
    $params = ['assignment' => $assignment->id, 'purpose' => 'posttest'];

    $this->actingAs($fixture['learner'])->postJson(route('user.training.quiz.start', $params))->assertForbidden();
    $this->patch(route('user.training.content.complete', $assignment))->assertRedirect();
    $start = $this->postJson(route('user.training.quiz.start', $params))->assertOk();
    $attempt = QuizAttempt::findOrFail($start->json('attempt_id'));
    $attempt->update(['deadline_at' => now()->subSecond()]);
    $questionId = $fixture['posttest']->questions()->sole()->id;
    $correctShuffledIndex = array_search(0, $attempt->option_orders[$questionId], true);

    $this->postJson(route('user.training.quiz.submit', $attempt), [
        'answers' => [$questionId => $correctShuffledIndex],
    ])->assertOk()->assertJsonPath('status', 'expired')->assertJsonPath('passed', false);

    expect($assignment->fresh()->score)->toBeNull()
        ->and($assignment->fresh()->status)->toBe('in_progress')
        ->and($assignment->fresh()->completed_at)->toBeNull();
});

test('three failed posttests are exhausted per assignment without completing it', function () {
    $fixture = moduleFinalFixture(false, true);
    $assignment = ModuleAssignment::create([
        'user_id' => $fixture['learner']->id, 'tenant_id' => $fixture['tenant']->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'in_progress',
        'content_started_at' => now(), 'content_completed_at' => now(),
    ]);
    foreach (range(1, 3) as $number) {
        QuizAttempt::create([
            'module_assignment_id' => $assignment->id, 'assessment_purpose' => 'posttest',
            'quiz_id' => $fixture['posttest']->id, 'user_id' => $fixture['learner']->id,
            'tenant_id' => $fixture['tenant']->id, 'status' => 'submitted',
            'started_at' => now()->subHours(8 - $number), 'submitted_at' => now()->subHours(7 - $number),
            'score' => 0, 'passed' => false,
        ]);
    }

    $state = app(AssessmentLifecycle::class)->state($assignment->fresh());
    expect($state['stage'])->toBe('attempts_exhausted')
        ->and($state['can_start_posttest'])->toBeFalse()
        ->and($assignment->fresh()->status)->toBe('in_progress')
        ->and($assignment->fresh()->completed_at)->toBeNull();
});

test('repeated start resumes one active attempt and database rejects another active row', function () {
    $fixture = moduleFinalFixture(true, false);
    $assignment = ModuleAssignment::create([
        'user_id' => $fixture['learner']->id, 'tenant_id' => $fixture['tenant']->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'assigned',
    ]);
    $params = ['assignment' => $assignment->id, 'purpose' => 'pretest'];

    $first = $this->actingAs($fixture['learner'])->postJson(route('user.training.quiz.start', $params))->assertOk();
    $second = $this->postJson(route('user.training.quiz.start', $params))->assertOk();
    expect($second->json('attempt_id'))->toBe($first->json('attempt_id'));

    expect(fn () => QuizAttempt::create([
        'module_assignment_id' => $assignment->id, 'assessment_purpose' => 'pretest',
        'quiz_id' => $fixture['pretest']->id, 'user_id' => $fixture['learner']->id,
        'tenant_id' => $fixture['tenant']->id, 'status' => 'in_progress', 'started_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('tenant admin cannot force mandatory assessment completion or mutate results', function () {
    $fixture = moduleFinalFixture();
    $assignment = ModuleAssignment::create([
        'user_id' => $fixture['learner']->id, 'tenant_id' => $fixture['tenant']->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'assigned',
    ]);

    $this->actingAs($fixture['admin'])->patch(route('tenant.assignments.update', $assignment), [
        'status' => 'completed', 'score' => 100, 'pretest_score' => 100,
        'content_started_at' => now(), 'content_completed_at' => now(),
        'posttest_quiz_id' => $fixture['pretest']->id,
        'module_snapshot' => ['content_html' => '<p>forged</p>'],
        'assigned_by' => $fixture['learner']->id,
    ])->assertSessionHasErrors([
        'status', 'score', 'pretest_score', 'content_started_at', 'content_completed_at',
        'posttest_quiz_id', 'module_snapshot', 'assigned_by',
    ]);

    expect($assignment->fresh()->status)->toBe('assigned')
        ->and($assignment->fresh()->score)->toBeNull()
        ->and($assignment->fresh()->content_completed_at)->toBeNull();
});

test('expired pretest finalizes an immutable baseline and cannot be retried', function () {
    $fixture = moduleFinalFixture(true, false);
    $assignment = ModuleAssignment::create([
        'user_id' => $fixture['learner']->id, 'tenant_id' => $fixture['tenant']->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'assigned',
    ]);
    $params = ['assignment' => $assignment->id, 'purpose' => 'pretest'];
    $start = $this->actingAs($fixture['learner'])->postJson(route('user.training.quiz.start', $params))->assertOk();
    $attempt = QuizAttempt::findOrFail($start->json('attempt_id'));
    $attempt->update(['deadline_at' => now()->subSecond()]);

    $this->postJson(route('user.training.quiz.start', $params))->assertUnprocessable();

    expect($attempt->fresh()->status)->toBe('expired')
        ->and($attempt->fresh()->passed)->toBeNull()
        ->and($assignment->fresh()->pretest_score)->toBe(0)
        ->and($assignment->fresh()->pretest_completed_at)->not->toBeNull();
    $this->postJson(route('user.training.quiz.start', $params))->assertUnprocessable();
    expect(QuizAttempt::where('module_assignment_id', $assignment->id)->count())->toBe(1);
});

test('late pretest answers are ignored when the server deadline has passed', function () {
    $fixture = moduleFinalFixture(true, false);
    $assignment = ModuleAssignment::create([
        'user_id' => $fixture['learner']->id, 'tenant_id' => $fixture['tenant']->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'assigned',
    ]);
    $params = ['assignment' => $assignment->id, 'purpose' => 'pretest'];
    $start = $this->actingAs($fixture['learner'])->postJson(route('user.training.quiz.start', $params))->assertOk();
    $attempt = QuizAttempt::findOrFail($start->json('attempt_id'));
    $attempt->update(['deadline_at' => now()->subSecond()]);
    $questionId = $fixture['pretest']->questions()->sole()->id;
    $correctShuffledIndex = array_search(0, $attempt->option_orders[$questionId], true);

    $this->postJson(route('user.training.quiz.submit', $attempt), [
        'answers' => [$questionId => $correctShuffledIndex],
    ])->assertOk()
        ->assertJsonPath('status', 'expired')
        ->assertJsonPath('score', 0)
        ->assertJsonPath('passed', null);

    expect($attempt->fresh()->answers)->toBeNull()
        ->and($attempt->fresh()->score)->toBe(0)
        ->and($assignment->fresh()->pretest_score)->toBe(0)
        ->and($assignment->fresh()->pretest_completed_at)->not->toBeNull();
});

test('a future assignment has a fresh posttest budget and overdue is derived only for open work', function () {
    $fixture = moduleFinalFixture(false, true);
    $old = ModuleAssignment::create([
        'user_id' => $fixture['learner']->id, 'tenant_id' => $fixture['tenant']->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'completed',
        'completed_at' => now(), 'content_started_at' => now(), 'content_completed_at' => now(),
    ]);
    foreach (range(1, 3) as $number) {
        QuizAttempt::create([
            'module_assignment_id' => $old->id, 'assessment_purpose' => 'posttest',
            'quiz_id' => $fixture['posttest']->id, 'user_id' => $fixture['learner']->id,
            'tenant_id' => $fixture['tenant']->id, 'status' => 'submitted',
            'started_at' => now()->subDays($number), 'submitted_at' => now()->subDays($number),
            'score' => 0, 'passed' => false,
        ]);
    }
    $fresh = ModuleAssignment::create([
        'user_id' => $fixture['learner']->id, 'tenant_id' => $fixture['tenant']->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'assigned',
        'deadline_at' => now()->subMinute(), 'content_started_at' => now(), 'content_completed_at' => now(),
    ]);

    $state = app(AssessmentLifecycle::class)->state($fresh);
    expect($state['posttest_attempts_used'])->toBe(0)
        ->and($state['can_start_posttest'])->toBeTrue()
        ->and($state['overdue'])->toBeTrue()
        ->and($old->isOverdue())->toBeFalse();
});
