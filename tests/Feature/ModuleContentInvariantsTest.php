<?php

use App\Models\Quiz;
use App\Models\TrainingModule;
use Illuminate\Database\UniqueConstraintViolationException;

test('invariant: XSS di content_html dilucuti saat save', function () {
    $module = TrainingModule::create([
        'title' => 'Test XSS',
        'content' => 'x',
        'content_html' => '<p>Halo</p><script>alert(1)</script><img src="x" onerror="alert(2)">',
        'duration_minutes' => 10,
        'status' => 'published',
        'is_active' => true,
    ]);

    $module->refresh();

    expect($module->content_html)->not->toContain('<script>')
        ->and($module->content_html)->not->toContain('onerror')
        ->and($module->content_html)->toContain('Halo');
});

test('invariant: maksimal 1 pretest per modul (DB partial unique index)', function () {
    $module = TrainingModule::create(['title' => 'M', 'content' => 'x', 'duration_minutes' => 5, 'status' => 'published', 'is_active' => true]);
    Quiz::create(['training_module_id' => $module->id, 'title' => 'Pre 1', 'passing_score' => 50, 'purpose' => 'pretest']);

    expect(fn () => Quiz::create(['training_module_id' => $module->id, 'title' => 'Pre 2', 'passing_score' => 50, 'purpose' => 'pretest']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('invariant: maksimal 1 posttest per modul (DB partial unique index)', function () {
    $module = TrainingModule::create(['title' => 'M', 'content' => 'x', 'duration_minutes' => 5, 'status' => 'published', 'is_active' => true]);
    Quiz::create(['training_module_id' => $module->id, 'title' => 'Post 1', 'passing_score' => 50, 'purpose' => 'posttest']);

    expect(fn () => Quiz::create(['training_module_id' => $module->id, 'title' => 'Post 2', 'passing_score' => 50, 'purpose' => 'posttest']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('invariant: pretest dan posttest koeksisten per modul', function () {
    $module = TrainingModule::create(['title' => 'M', 'content' => 'x', 'duration_minutes' => 5, 'status' => 'published', 'is_active' => true]);
    Quiz::create(['training_module_id' => $module->id, 'title' => 'Pre', 'passing_score' => 50, 'purpose' => 'pretest']);
    Quiz::create(['training_module_id' => $module->id, 'title' => 'Post', 'passing_score' => 50, 'purpose' => 'posttest']);

    expect(Quiz::where('training_module_id', $module->id)->count())->toBe(2);
});

test('invariant: purpose default = posttest (backward compat)', function () {
    $module = TrainingModule::create(['title' => 'M', 'content' => 'x', 'duration_minutes' => 5, 'status' => 'published', 'is_active' => true]);
    $quiz = Quiz::create(['training_module_id' => $module->id, 'title' => 'Q', 'passing_score' => 50]);

    expect($quiz->fresh()->purpose)->toBe('posttest');
});
