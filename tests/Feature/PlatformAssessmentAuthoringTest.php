<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
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
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

function authoringFixture(string $purpose = 'posttest'): array
{
    $super = User::factory()->superAdmin()->create();
    $module = TrainingModule::create([
        'title' => 'Authoring Module', 'content' => 'Version one', 'content_html' => '<p>Version one</p>',
        'duration_minutes' => 15, 'status' => 'draft', 'is_active' => true,
    ]);
    $quiz = Quiz::create([
        'training_module_id' => $module->id, 'purpose' => $purpose, 'title' => ucfirst($purpose).' A',
        'passing_score' => $purpose === 'pretest' ? 1 : 75, 'duration_minutes' => 20, 'is_active' => true,
    ]);
    $question = QuizQuestion::create([
        'quiz_id' => $quiz->id, 'question' => 'Original?', 'options' => ['Yes', 'No'],
        'correct_index' => 0, 'explanation' => 'Original explanation',
    ]);
    $module->update([$purpose.'_quiz_id' => $quiz->id]);

    return compact('super', 'module', 'quiz', 'question');
}

test('super admin can create and immediately bind a pretest without pass fail semantics', function () {
    $super = User::factory()->superAdmin()->create();
    $module = TrainingModule::create([
        'title' => 'New baseline', 'content' => 'x', 'duration_minutes' => 10, 'status' => 'draft',
    ]);

    $this->actingAs($super)->post(route('platform.quizzes.store'), [
        'training_module_id' => $module->id, 'purpose' => 'pretest', 'title' => 'Baseline',
        'duration_minutes' => 15, 'bind_as_current' => true,
    ])->assertRedirect();

    $quiz = Quiz::where('training_module_id', $module->id)->sole();
    expect($quiz->passing_score)->toBe(1)
        ->and($module->fresh()->pretest_quiz_id)->toBe($quiz->id);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'quiz.binding_changed', 'subject_id' => (string) $quiz->id,
    ]);
});

test('assessment creation rejects injected purpose and update rejects module reassignment', function () {
    $fixture = authoringFixture();
    $other = TrainingModule::create(['title' => 'Other module', 'content' => 'x', 'duration_minutes' => 10]);

    $this->actingAs($fixture['super'])->postJson(route('platform.quizzes.store'), [
        'training_module_id' => $fixture['module']->id, 'purpose' => 'pretest_admin',
        'title' => 'Injected', 'passing_score' => 70,
    ])->assertUnprocessable()->assertJsonValidationErrors('purpose');

    $this->patchJson(route('platform.quizzes.update', $fixture['quiz']), [
        'training_module_id' => $other->id, 'purpose' => 'posttest', 'title' => 'Moved', 'passing_score' => 70,
    ])->assertUnprocessable()->assertJsonValidationErrors('training_module_id');
    expect($fixture['quiz']->fresh()->training_module_id)->toBe($fixture['module']->id);
});

test('published module cannot bind an incomplete assessment during creation', function () {
    $super = User::factory()->superAdmin()->create();
    $module = TrainingModule::create([
        'title' => 'Published', 'content' => 'x', 'duration_minutes' => 10,
        'status' => 'published', 'is_active' => true,
    ]);

    $this->actingAs($super)->post(route('platform.quizzes.store'), [
        'training_module_id' => $module->id, 'purpose' => 'posttest', 'title' => 'Incomplete',
        'passing_score' => 70, 'bind_as_current' => true,
    ])->assertSessionHasErrors('quiz');

    $this->assertDatabaseMissing('quizzes', ['title' => 'Incomplete']);
    expect($module->fresh()->posttest_quiz_id)->toBeNull();
});

test('mutable assessment supports semantic update with pretest baseline semantics and audit', function () {
    $fixture = authoringFixture('pretest');

    $this->actingAs($fixture['super'])->patch(route('platform.quizzes.update', $fixture['quiz']), [
        'purpose' => 'pretest', 'title' => 'Baseline Baru', 'passing_score' => 99,
        'duration_minutes' => 12, 'is_active' => true,
    ])->assertRedirect();

    expect($fixture['quiz']->fresh())
        ->title->toBe('Baseline Baru')
        ->passing_score->toBe(1)
        ->duration_minutes->toBe(12);
    $this->assertDatabaseHas('audit_logs', ['action' => 'quiz.updated', 'subject_id' => (string) $fixture['quiz']->id]);
});

test('posttest requires passing score and accepts duration', function () {
    $fixture = authoringFixture();

    $this->actingAs($fixture['super'])->patchJson(route('platform.quizzes.update', $fixture['quiz']), [
        'purpose' => 'posttest', 'title' => 'Final', 'duration_minutes' => 30,
    ])->assertUnprocessable()->assertJsonValidationErrors('passing_score');

    $this->patch(route('platform.quizzes.update', $fixture['quiz']), [
        'purpose' => 'posttest', 'title' => 'Final', 'passing_score' => 85,
        'duration_minutes' => 30, 'is_active' => true,
    ])->assertRedirect();
    expect($fixture['quiz']->fresh()->passing_score)->toBe(85)
        ->and($fixture['quiz']->fresh()->duration_minutes)->toBe(30);
});

test('question create update and delete support explanation and audit', function () {
    $fixture = authoringFixture();
    $quiz = $fixture['quiz'];

    $this->actingAs($fixture['super'])->post(route('platform.quizzes.questions.store', $quiz), [
        'question' => 'Created?', 'options' => ['A', 'B', 'C'], 'correct_index' => 1, 'explanation' => 'Because B',
    ])->assertRedirect();
    $created = $quiz->questions()->where('question', 'Created?')->sole();
    $this->patch(route('platform.quizzes.questions.update', [$quiz, $created]), [
        'question' => 'Updated?', 'options' => ['One', 'Two'], 'correct_index' => 0, 'explanation' => 'Because one',
    ])->assertRedirect();
    expect($created->fresh()->question)->toBe('Updated?')->and($created->fresh()->explanation)->toBe('Because one');
    $this->delete(route('platform.quizzes.questions.destroy', [$quiz, $created]))->assertRedirect();
    $this->assertDatabaseMissing('quiz_questions', ['id' => $created->id]);
    expect(AuditLog::whereIn('action', ['question.created', 'question.updated', 'question.deleted'])->count())->toBe(3);
});

test('question validation enforces option bounds correct index and mass assignment boundary', function (array $payload, string $error) {
    $fixture = authoringFixture();

    $this->actingAs($fixture['super'])
        ->postJson(route('platform.quizzes.questions.store', $fixture['quiz']), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'fewer than two' => [['question' => 'Bad', 'options' => ['One'], 'correct_index' => 0], 'options'],
    'more than six' => [['question' => 'Bad', 'options' => ['1', '2', '3', '4', '5', '6', '7'], 'correct_index' => 0], 'options'],
    'out of range key' => [['question' => 'Bad', 'options' => ['1', '2'], 'correct_index' => 2], 'correct_index'],
    'forged quiz id' => [['quiz_id' => 999, 'question' => 'Bad', 'options' => ['1', '2'], 'correct_index' => 0], 'quiz_id'],
]);

test('wrong parent question update and delete return not found', function () {
    $fixture = authoringFixture();
    $other = authoringFixture();

    $this->actingAs($fixture['super'])->patch(route('platform.quizzes.questions.update', [$fixture['quiz'], $other['question']]), [
        'question' => 'Stolen', 'options' => ['A', 'B'], 'correct_index' => 0,
    ])->assertNotFound();
    $this->delete(route('platform.quizzes.questions.destroy', [$fixture['quiz'], $other['question']]))->assertNotFound();
    expect($other['question']->fresh()->question)->toBe('Original?');
});

test('every assessment semantic mutation is denied after assignment freeze', function () {
    $fixture = authoringFixture();
    $tenant = Tenant::factory()->create();
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    ModuleAssignment::create([
        'tenant_id' => $tenant->id, 'user_id' => $learner->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'assigned',
    ]);
    $this->actingAs($fixture['super']);

    $this->patch(route('platform.quizzes.update', $fixture['quiz']), [
        'purpose' => 'posttest', 'title' => 'Mutation', 'passing_score' => 90,
    ])->assertSessionHasErrors('quiz');
    $this->post(route('platform.quizzes.questions.store', $fixture['quiz']), [
        'question' => 'New?', 'options' => ['A', 'B'], 'correct_index' => 0,
    ])->assertSessionHasErrors('quiz');
    $this->patch(route('platform.quizzes.questions.update', [$fixture['quiz'], $fixture['question']]), [
        'question' => 'Mutation?', 'options' => ['A', 'B'], 'correct_index' => 0,
    ])->assertSessionHasErrors('quiz');
    $this->delete(route('platform.quizzes.questions.destroy', [$fixture['quiz'], $fixture['question']]))->assertSessionHasErrors('quiz');
    $this->delete(route('platform.quizzes.destroy', $fixture['quiz']))->assertSessionHasErrors('quiz');

    expect($fixture['quiz']->fresh()->title)->toBe(ucfirst($fixture['quiz']->purpose).' A')
        ->and($fixture['quiz']->questions()->count())->toBe(1);
});

test('an attempt freezes an assessment even when assignment snapshots do not reference it', function () {
    $super = User::factory()->superAdmin()->create();
    $module = TrainingModule::create([
        'title' => 'Attempt freeze', 'content' => 'x', 'duration_minutes' => 10, 'status' => 'draft',
    ]);
    $tenant = Tenant::factory()->create();
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $assignment = ModuleAssignment::create([
        'tenant_id' => $tenant->id, 'user_id' => $learner->id,
        'training_module_id' => $module->id, 'status' => 'assigned',
    ]);
    $quiz = Quiz::create([
        'training_module_id' => $module->id, 'purpose' => 'posttest',
        'title' => 'Attempt only', 'passing_score' => 70,
    ]);
    QuizAttempt::create([
        'tenant_id' => $tenant->id, 'user_id' => $learner->id,
        'quiz_id' => $quiz->id, 'module_assignment_id' => $assignment->id,
        'status' => 'in_progress', 'started_at' => now(), 'answers' => [],
    ]);

    expect($assignment->pretest_quiz_id)->toBeNull()
        ->and($assignment->posttest_quiz_id)->toBeNull();
    $this->actingAs($super)->patch(route('platform.quizzes.update', $quiz), [
        'purpose' => 'posttest', 'title' => 'Forbidden mutation', 'passing_score' => 80,
    ])->assertSessionHasErrors('quiz');
    expect($quiz->fresh()->title)->toBe('Attempt only');
});

test('replacement clones content but no attempts and binding preserves assignment history', function (string $purpose) {
    $fixture = authoringFixture($purpose);
    $tenant = Tenant::factory()->create();
    $firstLearner = User::factory()->create(['tenant_id' => $tenant->id]);
    $secondLearner = User::factory()->create(['tenant_id' => $tenant->id]);
    $old = ModuleAssignment::create([
        'tenant_id' => $tenant->id, 'user_id' => $firstLearner->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'completed', 'completed_at' => now(),
    ]);

    $this->actingAs($fixture['super'])->post(route('platform.quizzes.replace', $fixture['quiz']))->assertRedirect();
    $replacement = Quiz::where('training_module_id', $fixture['module']->id)->whereKeyNot($fixture['quiz']->id)->sole();
    expect($replacement->questions)->toHaveCount(1)
        ->and($replacement->attempts)->toHaveCount(0)
        ->and($fixture['module']->fresh()->getAttribute($purpose.'_quiz_id'))->toBe($fixture['quiz']->id);

    $this->post(route('platform.quizzes.bind', $replacement))->assertRedirect();
    $new = ModuleAssignment::create([
        'tenant_id' => $tenant->id, 'user_id' => $secondLearner->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'assigned',
    ]);
    expect($old->fresh()->getAttribute($purpose.'_quiz_id'))->toBe($fixture['quiz']->id)
        ->and($new->getAttribute($purpose.'_quiz_id'))->toBe($replacement->id)
        ->and(app(AssessmentAuthoringGuard::class)->isFrozen($replacement))->toBeTrue()
        ->and(AuditLog::where('action', 'quiz.replacement_created')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'quiz.binding_changed')->exists())->toBeTrue();
})->with(['pretest', 'posttest']);

test('module binding rejects a foreign quiz and a wrong purpose quiz', function (string $column, string $purpose) {
    $fixture = authoringFixture();
    $otherModule = TrainingModule::create([
        'title' => 'Other module', 'content' => 'x', 'duration_minutes' => 10, 'status' => 'draft',
    ]);
    $forgedQuiz = Quiz::create([
        'training_module_id' => $purpose === 'foreign' ? $otherModule->id : $fixture['module']->id,
        'purpose' => $purpose === 'foreign' ? 'posttest' : 'pretest',
        'title' => 'Forged binding', 'passing_score' => 70,
    ]);

    $payload = [
        'title' => $fixture['module']->title,
        'content_html' => '<p>Version one</p>',
        'duration_minutes' => 15,
        'status' => 'draft',
        'is_active' => true,
        'pretest_quiz_id' => null,
        'posttest_quiz_id' => $fixture['quiz']->id,
    ];
    $payload[$column] = $forgedQuiz->id;

    $this->actingAs($fixture['super'])
        ->patchJson(route('platform.modules.update', $fixture['module']), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($column);
    expect($fixture['module']->fresh()->posttest_quiz_id)->toBe($fixture['quiz']->id);
})->with([
    'foreign module quiz' => ['posttest_quiz_id', 'foreign'],
    'wrong purpose quiz' => ['posttest_quiz_id', 'wrong-purpose'],
]);

test('published current mutable assessment cannot be made unusable', function () {
    $fixture = authoringFixture();
    $fixture['module']->update(['status' => 'published']);

    $this->actingAs($fixture['super'])->patch(route('platform.quizzes.update', $fixture['quiz']), [
        'purpose' => 'posttest', 'title' => 'Disabled', 'passing_score' => 80,
        'duration_minutes' => 20, 'is_active' => false,
    ])->assertSessionHasErrors('quiz');
    expect($fixture['quiz']->fresh()->is_active)->toBeTrue()
        ->and($fixture['quiz']->fresh()->title)->toBe('Posttest A');

    $this->delete(route('platform.quizzes.questions.destroy', [$fixture['quiz'], $fixture['question']]))
        ->assertSessionHasErrors('quiz');
    $this->assertDatabaseHas('quiz_questions', ['id' => $fixture['question']->id]);
});

test('replacement safely bounds a maximum length title', function () {
    $fixture = authoringFixture();
    $fixture['quiz']->update(['title' => str_repeat('A', 255)]);
    $tenant = Tenant::factory()->create();
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    ModuleAssignment::create([
        'tenant_id' => $tenant->id, 'user_id' => $learner->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'assigned',
    ]);

    $this->actingAs($fixture['super'])->post(route('platform.quizzes.replace', $fixture['quiz']))->assertRedirect();
    $replacement = Quiz::whereKeyNot($fixture['quiz']->id)->where('training_module_id', $fixture['module']->id)->sole();
    expect(mb_strlen($replacement->title))->toBeLessThanOrEqual(255)
        ->and($replacement->title)->toEndWith(' (Pengganti)');
});

test('replacement rolls back completely when question cloning fails', function () {
    $fixture = authoringFixture();
    QuizQuestion::create([
        'quiz_id' => $fixture['quiz']->id, 'question' => 'Second?',
        'options' => ['Yes', 'No'], 'correct_index' => 0,
    ]);
    $tenant = Tenant::factory()->create();
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    ModuleAssignment::create([
        'tenant_id' => $tenant->id, 'user_id' => $learner->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'assigned',
    ]);
    $event = 'eloquent.creating: '.QuizQuestion::class;
    Event::listen($event, function (QuizQuestion $question): void {
        if ($question->question === 'Second?') {
            throw new RuntimeException('Forced clone failure');
        }
    });

    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->actingAs($fixture['super'])
            ->post(route('platform.quizzes.replace', $fixture['quiz'])))->toThrow(RuntimeException::class);
    } finally {
        Event::forget($event);
    }

    expect(Quiz::where('training_module_id', $fixture['module']->id)->count())->toBe(1)
        ->and(AuditLog::where('action', 'quiz.replacement_created')->exists())->toBeFalse();
});

test('replacement keeps an existing learner attempt and result unchanged', function () {
    $fixture = authoringFixture();
    $tenant = Tenant::factory()->create();
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $assignment = ModuleAssignment::create([
        'tenant_id' => $tenant->id, 'user_id' => $learner->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'completed', 'score' => 80, 'completed_at' => now(),
    ]);
    $attempt = QuizAttempt::create([
        'tenant_id' => $tenant->id, 'user_id' => $learner->id, 'quiz_id' => $fixture['quiz']->id,
        'module_assignment_id' => $assignment->id, 'status' => 'submitted', 'score' => 80, 'passed' => true,
        'answers' => [], 'started_at' => now(), 'submitted_at' => now(),
    ]);

    $this->actingAs($fixture['super'])->post(route('platform.quizzes.replace', $fixture['quiz']))->assertRedirect();
    expect($attempt->fresh()->quiz_id)->toBe($fixture['quiz']->id)
        ->and($attempt->fresh()->score)->toBe(80)
        ->and($assignment->fresh()->score)->toBe(80);
});

test('preview is read only and exposes author answer key only on platform page', function () {
    $fixture = authoringFixture();
    $before = [QuizAttempt::count(), ModuleAssignment::count()];

    $this->actingAs($fixture['super'])->get(route('platform.quizzes.preview', $fixture['quiz']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Platform/Quizzes/Show')->where('preview', true)
        ->where('quiz.questions.0.correct_index', 0));
    expect([QuizAttempt::count(), ModuleAssignment::count()])->toBe($before);
});

test('quiz list reports current historical and mutable frozen states without pretest passing semantics', function () {
    $fixture = authoringFixture('pretest');

    $this->actingAs($fixture['super'])->get(route('platform.quizzes.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizzes.0.is_current', true)
            ->where('quizzes.0.lifecycle_state', 'current')
            ->where('quizzes.0.is_mutable', true)
            ->where('quizzes.0.passing_score', null));
});

test('publish rejects configured zero question and inactive assessments but permits no assessment', function (string $invalid) {
    $super = User::factory()->superAdmin()->create();
    $module = TrainingModule::create([
        'title' => 'Publish', 'content' => 'x', 'duration_minutes' => 10, 'status' => 'draft', 'is_active' => true,
    ]);
    $quiz = Quiz::create([
        'training_module_id' => $module->id, 'purpose' => 'posttest', 'title' => 'Invalid',
        'passing_score' => 70, 'is_active' => $invalid !== 'inactive',
    ]);
    if ($invalid === 'inactive') {
        QuizQuestion::create(['quiz_id' => $quiz->id, 'question' => 'Q', 'options' => ['A', 'B'], 'correct_index' => 0]);
    }
    $module->update(['posttest_quiz_id' => $quiz->id]);

    $this->actingAs($super)->post(route('platform.modules.publish', $module))
        ->assertSessionHasErrors('posttest_quiz_id');
    expect($module->fresh()->status)->toBe('draft');

    $module->update(['posttest_quiz_id' => null]);
    $this->post(route('platform.modules.publish', $module))->assertRedirect();
    expect($module->fresh()->status)->toBe('published');
})->with(['zero questions', 'inactive']);

test('published update may explicitly remove an invalid assessment binding', function () {
    $fixture = authoringFixture();
    $fixture['question']->delete();

    $this->actingAs($fixture['super'])->patch(route('platform.modules.update', $fixture['module']), [
        'title' => $fixture['module']->title, 'content_html' => '<p>Valid content</p>',
        'duration_minutes' => 15, 'status' => 'published', 'is_active' => true,
        'pretest_quiz_id' => null, 'posttest_quiz_id' => null,
    ])->assertRedirect();

    expect($fixture['module']->fresh()->status)->toBe('published')
        ->and($fixture['module']->fresh()->posttest_quiz_id)->toBeNull();
});

test('module preview is read only and archive disables future use while historical delete is denied', function () {
    $fixture = authoringFixture();
    $tenant = Tenant::factory()->create();
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    ModuleAssignment::create([
        'tenant_id' => $tenant->id, 'user_id' => $learner->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'assigned',
    ]);

    $this->actingAs($fixture['super'])->get(route('platform.modules.preview', $fixture['module']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('preview', true));
    $this->post(route('platform.modules.archive', $fixture['module']))->assertRedirect();
    expect($fixture['module']->fresh()->status)->toBe('archived')->and($fixture['module']->fresh()->is_active)->toBeFalse();
    $this->delete(route('platform.modules.destroy', $fixture['module']))->assertSessionHasErrors('module');
    $this->assertDatabaseHas('training_modules', ['id' => $fixture['module']->id]);
});

test('archived module cannot receive a new tenant assignment', function () {
    $super = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $package = Package::create([
        'name' => 'Archive '.uniqid(), 'slug' => 'archive-'.uniqid(), 'price_monthly' => 100,
        'max_users' => 10, 'features' => ['training'], 'includes_all_modules' => true, 'is_active' => true,
    ]);
    Subscription::create([
        'tenant_id' => $tenant->id, 'package_id' => $package->id, 'status' => 'active', 'started_at' => now(),
    ]);
    $module = TrainingModule::create([
        'title' => 'Archive race', 'content' => 'x', 'duration_minutes' => 10,
        'status' => 'published', 'is_active' => true,
    ]);

    $this->actingAs($super)->post(route('platform.modules.archive', $module))->assertRedirect();
    $this->actingAs($admin)->post(route('tenant.assignments.store'), [
        'user_id' => $learner->id, 'training_module_id' => $module->id,
    ])->assertSessionHasErrors('training_module_id');
    expect(ModuleAssignment::count())->toBe(0);
});

test('module edits affect new snapshots only', function () {
    $fixture = authoringFixture();
    $tenant = Tenant::factory()->create();
    $first = User::factory()->create(['tenant_id' => $tenant->id]);
    $second = User::factory()->create(['tenant_id' => $tenant->id]);
    $old = ModuleAssignment::create([
        'tenant_id' => $tenant->id, 'user_id' => $first->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'completed', 'completed_at' => now(),
    ]);

    $this->actingAs($fixture['super'])->patch(route('platform.modules.update', $fixture['module']), [
        'title' => 'Version two', 'content_html' => '<p>Version two</p>', 'duration_minutes' => 20,
        'status' => 'draft', 'is_active' => true, 'posttest_quiz_id' => $fixture['quiz']->id,
    ])->assertRedirect();
    $new = ModuleAssignment::create([
        'tenant_id' => $tenant->id, 'user_id' => $second->id,
        'training_module_id' => $fixture['module']->id, 'status' => 'assigned',
    ]);
    expect($old->fresh()->module_snapshot['title'])->toBe('Authoring Module')
        ->and($new->module_snapshot['title'])->toBe('Version two');
});

test('tenant admin learner and inactive super admin cannot author platform assessments', function (string $role, bool $active) {
    $fixture = authoringFixture();
    $user = $role === 'super_admin'
        ? User::factory()->superAdmin()->create(['is_active' => $active])
        : User::factory()->create(['role' => UserRole::from($role), 'is_active' => $active]);

    $this->actingAs($user)->get(route('platform.quizzes.index'))->assertForbidden();
    $this->post(route('platform.quizzes.store'), [])->assertForbidden();
    $this->patch(route('platform.quizzes.update', $fixture['quiz']), [])->assertForbidden();
    $this->post(route('platform.quizzes.replace', $fixture['quiz']))->assertForbidden();
    $this->post(route('platform.quizzes.bind', $fixture['quiz']))->assertForbidden();
    $this->delete(route('platform.quizzes.destroy', $fixture['quiz']))->assertForbidden();
    $this->post(route('platform.quizzes.questions.store', $fixture['quiz']), [])->assertForbidden();
    $this->patch(route('platform.quizzes.questions.update', [$fixture['quiz'], $fixture['question']]), [])->assertForbidden();
    $this->delete(route('platform.quizzes.questions.destroy', [$fixture['quiz'], $fixture['question']]))->assertForbidden();
    $this->get(route('platform.modules.preview', $fixture['module']))->assertForbidden();
    $this->patch(route('platform.modules.update', $fixture['module']), [])->assertForbidden();
    $this->post(route('platform.modules.publish', $fixture['module']))->assertForbidden();
    $this->post(route('platform.modules.archive', $fixture['module']))->assertForbidden();
    $this->delete(route('platform.modules.destroy', $fixture['module']))->assertForbidden();
})->with([
    'tenant admin' => ['tenant_admin', true],
    'learner' => ['user', true],
    'inactive super' => ['super_admin', false],
]);

test('unused non-current draft assessment can be deleted but current assessment cannot', function () {
    $fixture = authoringFixture();
    $unused = Quiz::create([
        'training_module_id' => $fixture['module']->id, 'purpose' => 'posttest', 'title' => 'Unused', 'passing_score' => 70,
    ]);

    $this->actingAs($fixture['super'])->delete(route('platform.quizzes.destroy', $fixture['quiz']))->assertSessionHasErrors('quiz');
    $this->delete(route('platform.quizzes.destroy', $unused))->assertRedirect(route('platform.quizzes.index'));
    $this->assertDatabaseMissing('quizzes', ['id' => $unused->id]);
});

test('unused module deletion audit retains the stable module id', function () {
    $super = User::factory()->superAdmin()->create();
    $module = TrainingModule::create(['title' => 'Disposable', 'content' => 'x', 'duration_minutes' => 10]);

    $this->actingAs($super)->delete(route('platform.modules.destroy', $module))
        ->assertRedirect(route('platform.modules.index'));

    $audit = AuditLog::where('action', 'module.deleted')->sole();
    expect($audit->properties['module_id'])->toBe($module->id)
        ->and($audit->properties['title'])->toBe('Disposable');
});
