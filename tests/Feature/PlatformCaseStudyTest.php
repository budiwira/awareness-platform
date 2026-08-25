<?php

use App\Models\CaseStudy;
use App\Models\User;

test('super admin can create case study', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)
        ->post(route('platform.cases.store'), [
            'title' => 'Insiden Ransomware',
            'difficulty' => 'intermediate',
            'duration_minutes' => 20,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('case_studies', ['title' => 'Insiden Ransomware']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'case.created']);
});

test('super admin can add scene with quality options', function () {
    $super = User::factory()->superAdmin()->create();
    $case = CaseStudy::create(['title' => 'C', 'difficulty' => 'beginner', 'duration_minutes' => 10]);

    $this->actingAs($super)
        ->post(route('platform.cases.scenes.store', $case), [
            'situation' => 'Karyawan menerima email mencurigakan.',
            'options' => [
                ['text' => 'Lapor ke tim IT', 'quality' => 'best', 'feedback' => 'Benar, eskalasi adalah langkah tepat.'],
                ['text' => 'Klik link untuk cek', 'quality' => 'poor', 'feedback' => 'Berbahaya, jangan klik tautan mencurigakan.'],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('case_scenes', ['case_study_id' => $case->id]);
});

test('invalid quality is rejected', function () {
    $super = User::factory()->superAdmin()->create();
    $case = CaseStudy::create(['title' => 'C', 'difficulty' => 'beginner', 'duration_minutes' => 10]);

    $this->actingAs($super)
        ->post(route('platform.cases.scenes.store', $case), [
            'situation' => 'Situasi.',
            'options' => [
                ['text' => 'A', 'quality' => 'invalid', 'feedback' => 'x'],
                ['text' => 'B', 'quality' => 'best', 'feedback' => 'y'],
            ],
        ])
        ->assertSessionHasErrors('options.0.quality');
});

test('tenant admin cannot access case study management', function () {
    $admin = User::factory()->tenantAdmin()->create();

    $this->actingAs($admin)->get(route('platform.cases.index'))->assertForbidden();
});