<?php

use App\Models\Quiz;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('super admin can list training modules', function () {
    $super = User::factory()->superAdmin()->create();
    TrainingModule::create(['title' => 'Phishing 101', 'content' => 'Materi...', 'duration_minutes' => 10]);

    $this->actingAs($super)
        ->get(route('platform.modules.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Platform/TrainingModules/Index'));
});

test('super admin can create a training module', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)
        ->post(route('platform.modules.store'), [
            'title' => 'Password Security',
            'content_html' => 'Gunakan password kuat...',
            'duration_minutes' => 15,
            'status' => 'published',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('training_modules', ['title' => 'Password Security']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'module.created']);
});

test('platform module preview receives sanitized rich content', function () {
    $super = User::factory()->superAdmin()->create();
    $module = TrainingModule::create([
        'title' => 'Rich preview', 'content' => 'Plain derivative',
        'content_html' => '<h2>Rich heading</h2><script>alert(1)</script>',
        'duration_minutes' => 10,
    ]);

    $this->actingAs($super)->get(route('platform.modules.show', $module))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/TrainingModules/Show')
            ->where('module.content_html', '<h2>Rich heading</h2>')
            ->where('module.content', 'Rich heading')
        );
});

test('tenant admin cannot access platform modules', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->get(route('platform.modules.index'))->assertForbidden();
});

test('module wizard only offers quizzes belonging to the edited module', function () {
    $super = User::factory()->superAdmin()->create();
    $module = TrainingModule::create(['title' => 'Module', 'content' => 'Materi', 'duration_minutes' => 10]);
    $other = TrainingModule::create(['title' => 'Other', 'content' => 'Materi', 'duration_minutes' => 10]);
    $quiz = Quiz::create(['training_module_id' => $module->id, 'title' => 'Pretest', 'purpose' => 'pretest', 'passing_score' => 50]);
    Quiz::create(['training_module_id' => $other->id, 'title' => 'Other pretest', 'purpose' => 'pretest', 'passing_score' => 50]);

    $this->actingAs($super)->get(route('platform.modules.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Platform/TrainingModules/Wizard')->has('quizzes', 0));
    $this->get(route('platform.modules.edit', $module))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Platform/TrainingModules/Wizard')
            ->has('quizzes', 1)->where('quizzes.0.id', $quiz->id)->where('quizzes.0.purpose', 'pretest'));
});

test('module creation rejects existing quiz IDs even in a forged request', function () {
    $super = User::factory()->superAdmin()->create();
    $module = TrainingModule::create(['title' => 'Other', 'content' => 'Materi', 'duration_minutes' => 10]);
    $quiz = Quiz::create(['training_module_id' => $module->id, 'title' => 'Pretest', 'purpose' => 'pretest', 'passing_score' => 50]);
    $this->actingAs($super)->postJson(route('platform.modules.store'), [
        'title' => 'New', 'content_html' => '<p>Materi</p>', 'duration_minutes' => 10, 'status' => 'draft',
        'pretest_quiz_id' => $quiz->id, 'posttest_quiz_id' => $quiz->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['pretest_quiz_id', 'posttest_quiz_id']);
    $this->assertDatabaseMissing('training_modules', ['title' => 'New']);
});

test('module update validates quiz module and purpose server side', function ($selection) {
    $super = User::factory()->superAdmin()->create();
    $module = TrainingModule::create(['title' => 'Module', 'content' => 'Materi', 'duration_minutes' => 10]);
    $other = TrainingModule::create(['title' => 'Other', 'content' => 'Materi', 'duration_minutes' => 10]);
    $quizModule = $selection === 'other module' ? $other : $module;
    $pre = Quiz::create(['training_module_id' => $quizModule->id, 'title' => 'Pretest', 'purpose' => 'pretest', 'passing_score' => 50]);
    $post = Quiz::create(['training_module_id' => $quizModule->id, 'title' => 'Posttest', 'purpose' => 'posttest', 'passing_score' => 50]);
    $response = $this->actingAs($super)->patchJson(route('platform.modules.update', $module), [
        'title' => 'Module', 'content_html' => '<p>Materi</p>', 'duration_minutes' => 10, 'status' => 'draft', 'is_active' => true,
        'pretest_quiz_id' => $selection === 'wrong purpose' ? $post->id : $pre->id,
        'posttest_quiz_id' => $selection === 'wrong purpose' ? $pre->id : $post->id,
    ]);
    if ($selection === 'valid') {
        $response->assertRedirect();
        expect($module->fresh()->pretest_quiz_id)->toBe($pre->id)->and($module->fresh()->posttest_quiz_id)->toBe($post->id);
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors(['pretest_quiz_id', 'posttest_quiz_id']);
        expect($module->fresh()->pretest_quiz_id)->toBeNull()->and($module->fresh()->posttest_quiz_id)->toBeNull();
    }
})->with(['valid', 'other module', 'wrong purpose']);

test('tenant admin cannot use module wizard or change quiz links across tenants', function () {
    $admin = User::factory()->tenantAdmin()->create();
    $module = TrainingModule::create([
        'tenant_id' => Tenant::factory()->create()->id,
        'title' => 'Other tenant', 'content' => 'Materi', 'duration_minutes' => 10,
    ]);
    $this->actingAs($admin)->get(route('platform.modules.create'))->assertForbidden();
    $this->get(route('platform.modules.edit', $module))->assertForbidden();
    $this->postJson(route('platform.modules.store'), [])->assertForbidden();
    $this->patchJson(route('platform.modules.update', $module), [])->assertForbidden();
});

test('module with invalid historical quiz bindings cannot be published', function () {
    $super = User::factory()->superAdmin()->create();
    $module = TrainingModule::create([
        'title' => 'Invalid binding', 'content' => 'Materi', 'duration_minutes' => 10, 'status' => 'draft',
    ]);
    $other = TrainingModule::create(['title' => 'Other', 'content' => 'Materi', 'duration_minutes' => 10]);
    $wrongPretest = Quiz::create([
        'training_module_id' => $other->id, 'title' => 'Wrong pretest',
        'purpose' => 'posttest', 'passing_score' => 50,
    ]);
    $module->update(['pretest_quiz_id' => $wrongPretest->id]);

    $this->actingAs($super)->post(route('platform.modules.publish', $module))
        ->assertSessionHasErrors('pretest_quiz_id');

    expect($module->fresh()->status)->toBe('draft')
        ->and($module->fresh()->pretest_quiz_id)->toBe($wrongPretest->id);
});
