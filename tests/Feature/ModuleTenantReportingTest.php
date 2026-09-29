<?php

use App\Models\AuditLog;
use App\Models\ModuleAssignment;
use App\Models\Package;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use App\Services\Reporting\TenantReportService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");
});

function reportingModule(bool $pretest = true, bool $posttest = true): array
{
    $module = TrainingModule::create([
        'title' => 'Modul Pelaporan '.uniqid(),
        'content' => 'Materi',
        'duration_minutes' => 20,
        'status' => 'published',
        'is_active' => true,
    ]);
    $pre = $pretest ? Quiz::create([
        'training_module_id' => $module->id,
        'title' => 'Pretest',
        'passing_score' => 0,
        'purpose' => 'pretest',
        'is_active' => true,
    ]) : null;
    $post = $posttest ? Quiz::create([
        'training_module_id' => $module->id,
        'title' => 'Posttest',
        'passing_score' => 70,
        'purpose' => 'posttest',
        'is_active' => true,
    ]) : null;
    $module->update(['pretest_quiz_id' => $pre?->id, 'posttest_quiz_id' => $post?->id]);

    return compact('module', 'pre', 'post');
}

function reportingAssignment(Tenant $tenant, User $user, array $fixture, array $attributes = []): ModuleAssignment
{
    return ModuleAssignment::create(array_merge([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'training_module_id' => $fixture['module']->id,
        'pretest_quiz_id' => $fixture['pre']?->id,
        'posttest_quiz_id' => $fixture['post']?->id,
        'status' => 'assigned',
        'assigned_at' => now()->subDays(3),
    ], $attributes));
}

function reportingAttempt(ModuleAssignment $assignment, Quiz $quiz, string $status, ?int $score, bool $passed = false): QuizAttempt
{
    return QuizAttempt::create([
        'module_assignment_id' => $assignment->id,
        'quiz_id' => $quiz->id,
        'assessment_purpose' => $quiz->purpose,
        'user_id' => $assignment->user_id,
        'tenant_id' => $assignment->tenant_id,
        'status' => $status,
        'started_at' => now()->subMinutes(15),
        'submitted_at' => in_array($status, ['submitted', 'expired'], true) ? now() : null,
        'score' => $score,
        'passed' => $passed,
        'answers' => [],
    ]);
}

test('module summary keeps repeated learner module cycles and replacement bindings assignment scoped', function () {
    $tenant = Tenant::factory()->create();
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $fixture = reportingModule();
    $completed = reportingAssignment($tenant, $learner, $fixture, [
        'status' => 'completed', 'pretest_score' => 40, 'pretest_completed_at' => now()->subDays(2),
        'score' => 80, 'completed_at' => now()->subDay(),
    ]);
    reportingAttempt($completed, $fixture['pre'], 'submitted', 40);
    reportingAttempt($completed, $fixture['post'], 'submitted', 80, true);
    $replacementPre = Quiz::create([
        'training_module_id' => $fixture['module']->id,
        'title' => 'Pretest Replacement',
        'passing_score' => 0,
        'purpose' => 'pretest',
        'is_active' => true,
    ]);
    $replacementPost = Quiz::create([
        'training_module_id' => $fixture['module']->id,
        'title' => 'Posttest Replacement',
        'passing_score' => 70,
        'purpose' => 'posttest',
        'is_active' => true,
    ]);
    $fixture['module']->update(['pretest_quiz_id' => $replacementPre->id, 'posttest_quiz_id' => $replacementPost->id]);
    $replacement = ['module' => $fixture['module'], 'pre' => $replacementPre, 'post' => $replacementPost];
    $current = reportingAssignment($tenant, $learner, $replacement, [
        'status' => 'in_progress', 'pretest_score' => 60, 'pretest_completed_at' => now()->subDay(),
    ]);
    reportingAttempt($current, $replacement['pre'], 'submitted', 60);
    reportingAttempt($current, $fixture['post'], 'submitted', 100, true);
    reportingAttempt($completed, $replacement['post'], 'submitted', 100, true);

    $summary = app(TenantReportService::class)->getExecutiveSummary($tenant->id);

    expect($summary)
        ->total_assignments->toBe(2)
        ->completed_assignments->toBe(1)
        ->completion_rate->toBe(50.0)
        ->avg_baseline_score->toBe(50.0)
        ->avg_best_posttest_score->toBe(80.0)
        ->avg_learning_gain->toBe(40.0)
        ->posttest_result_count->toBe(1);
});

test('baseline requires terminal evidence for the frozen pretest binding and preserves a real zero', function () {
    $tenant = Tenant::factory()->create();
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $fixture = reportingModule();
    $missing = reportingAssignment($tenant, $learner, $fixture, [
        'pretest_score' => 91, 'pretest_completed_at' => now(),
    ]);
    $zero = reportingAssignment($tenant, $learner, $fixture, [
        'status' => 'completed', 'pretest_score' => 0, 'pretest_completed_at' => now(), 'completed_at' => now(),
    ]);
    reportingAttempt($zero, $fixture['pre'], 'expired', 0);
    reportingAttempt($zero, $fixture['post'], 'submitted', 0);

    $tracking = collect(app(TenantReportService::class)->getAssignmentTracking($tenant->id));
    $summary = app(TenantReportService::class)->getExecutiveSummary($tenant->id);

    expect($tracking->firstWhere('id', $missing->id)['pretest_baseline'])->toBeNull()
        ->and($tracking->firstWhere('id', $zero->id)['pretest_baseline'])->toBe(0)
        ->and($summary)->baseline_count->toBe(1)->avg_baseline_score->toBe(0.0)
        ->posttest_result_count->toBe(1)->avg_best_posttest_score->toBe(0.0)
        ->gain_count->toBe(1)->avg_learning_gain->toBe(0.0);
});

test('cancelled assignments are historical only and overdue uses active lifecycle states', function () {
    $tenant = Tenant::factory()->create();
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $fixture = reportingModule();
    $complete = reportingAssignment($tenant, $learner, $fixture, [
        'status' => 'completed', 'pretest_score' => 50, 'pretest_completed_at' => now(),
        'score' => 90, 'completed_at' => now(), 'deadline_at' => now()->subDay(),
    ]);
    reportingAttempt($complete, $fixture['pre'], 'submitted', 50);
    reportingAttempt($complete, $fixture['post'], 'submitted', 90, true);
    reportingAssignment($tenant, $learner, $fixture, ['status' => 'assigned', 'deadline_at' => now()->subDay()]);
    $cancelled = reportingAssignment($tenant, $learner, $fixture, [
        'status' => 'cancelled', 'cancelled_at' => now(), 'deadline_at' => now()->subDay(),
        'pretest_score' => 0, 'pretest_completed_at' => now(), 'score' => 100, 'completed_at' => now(),
    ]);
    reportingAttempt($cancelled, $fixture['pre'], 'submitted', 0);
    reportingAttempt($cancelled, $fixture['post'], 'submitted', 100, true);

    $service = app(TenantReportService::class);
    $summary = $service->getExecutiveSummary($tenant->id);
    $tracking = collect($service->getAssignmentTracking($tenant->id));

    expect($summary)
        ->total_assignments->toBe(2)
        ->completed_assignments->toBe(1)
        ->completion_rate->toBe(50.0)
        ->overdue_count->toBe(1)
        ->baseline_count->toBe(1)
        ->posttest_result_count->toBe(1)
        ->gain_count->toBe(1)
        ->posttest_participation_count->toBe(1)
        ->posttest_passed_count->toBe(1)
        ->and($tracking->firstWhere('id', $cancelled->id)['display_status'])->toBe('cancelled');
});

test('empty report keeps zero counts separate from unavailable averages', function () {
    $tenant = Tenant::factory()->create();

    $summary = app(TenantReportService::class)->getExecutiveSummary($tenant->id);

    expect($summary)->total_assignments->toBe(0)->completed_assignments->toBe(0)
        ->completion_rate->toBe(0.0)->overdue_count->toBe(0)
        ->baseline_count->toBe(0)->avg_baseline_score->toBeNull()
        ->posttest_result_count->toBe(0)->avg_best_posttest_score->toBeNull()
        ->gain_count->toBe(0)->avg_learning_gain->toBeNull()
        ->posttest_participation_count->toBe(0)->posttest_pass_rate->toBeNull();
});

test('expired posttest participates without creating a result while submitted zero remains valid', function () {
    $tenant = Tenant::factory()->create();
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $fixture = reportingModule();
    $expired = reportingAssignment($tenant, $learner, $fixture, [
        'status' => 'in_progress', 'pretest_score' => 50, 'pretest_completed_at' => now(), 'score' => 0,
    ]);
    reportingAttempt($expired, $fixture['pre'], 'submitted', 50);
    reportingAttempt($expired, $fixture['post'], 'expired', 0);
    reportingAttempt($expired, $fixture['post'], 'expired', 0);
    reportingAttempt($expired, $fixture['post'], 'expired', 0);
    $zero = reportingAssignment($tenant, $learner, $fixture, [
        'status' => 'completed', 'pretest_score' => 0, 'pretest_completed_at' => now(),
        'score' => 0, 'completed_at' => now(),
    ]);
    reportingAttempt($zero, $fixture['pre'], 'submitted', 0);
    reportingAttempt($zero, $fixture['post'], 'submitted', 0, false);

    $summary = app(TenantReportService::class)->getExecutiveSummary($tenant->id);
    $expiredRow = collect(app(TenantReportService::class)->getAssignmentTracking($tenant->id))->firstWhere('id', $expired->id);

    expect($summary)
        ->posttest_participation_count->toBe(2)
        ->posttest_result_count->toBe(1)
        ->avg_best_posttest_score->toBe(0.0)
        ->gain_count->toBe(1)
        ->avg_learning_gain->toBe(0.0)
        ->posttest_pass_rate->toBe(0.0)
        ->and($expiredRow['best_posttest'])->toBeNull()
        ->and($expiredRow['learning_gain'])->toBeNull()
        ->and($expiredRow['display_status'])->toBe('needs_follow_up')
        ->and($expired->fresh()->score)->toBe(0);
});

test('posttest pass rate and learning gain follow terminal and eligibility rules', function () {
    $tenant = Tenant::factory()->create();
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $fixture = reportingModule();
    $cases = [
        [50, 'submitted', 80, true],
        [80, 'submitted', 60, false],
        [70, 'expired', 0, false],
        [null, null, null, false],
    ];
    foreach ($cases as [$baseline, $status, $score, $passed]) {
        $assignment = reportingAssignment($tenant, $learner, $fixture, [
            'status' => 'completed',
            'pretest_score' => $baseline,
            'pretest_completed_at' => $baseline === null ? null : now(),
            'completed_at' => now(),
        ]);
        if ($status !== null) {
            reportingAttempt($assignment, $fixture['post'], $status, $score, $passed);
        }
        if ($baseline !== null) {
            reportingAttempt($assignment, $fixture['pre'], 'submitted', $baseline);
        }
    }
    $noPosttest = reportingModule(true, false);
    reportingAssignment($tenant, $learner, $noPosttest, ['status' => 'completed', 'completed_at' => now()]);

    $summary = app(TenantReportService::class)->getExecutiveSummary($tenant->id);

    expect($summary)
        ->posttest_participation_count->toBe(3)
        ->posttest_passed_count->toBe(1)
        ->posttest_pass_rate->toBe(33.3)
        ->gain_count->toBe(2)
        ->avg_learning_gain->toBe(5.0);
});

test('posttest reporting rejects wrong purpose quiz and assignment bindings', function () {
    $tenant = Tenant::factory()->create();
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $fixture = reportingModule();
    $other = reportingModule();
    $assignment = reportingAssignment($tenant, $learner, $fixture, ['status' => 'in_progress']);
    $otherAssignment = reportingAssignment($tenant, $learner, $other, ['status' => 'completed', 'completed_at' => now()]);

    reportingAttempt($assignment, $fixture['pre'], 'submitted', 100, true);
    reportingAttempt($assignment, $other['post'], 'submitted', 100, true);
    reportingAttempt($otherAssignment, $fixture['post'], 'submitted', 100, true);
    $wrongPurpose = reportingAttempt($assignment, $fixture['post'], 'submitted', 100, true);
    $wrongPurpose->update(['assessment_purpose' => 'practice']);

    $summary = app(TenantReportService::class)->getExecutiveSummary($tenant->id);
    $row = collect(app(TenantReportService::class)->getAssignmentTracking($tenant->id))->firstWhere('id', $assignment->id);

    expect($summary)->posttest_result_count->toBe(0)->posttest_participation_count->toBe(0)
        ->posttest_passed_count->toBe(0)->posttest_pass_rate->toBeNull()
        ->and($row['best_posttest'])->toBeNull()
        ->and($row['posttest_passed'])->toBeFalse();
});

test('overdue uses strict active status and deadline before now semantics', function () {
    $tenant = Tenant::factory()->create();
    $fixture = reportingModule(false, false);
    $pastAssigned = reportingAssignment($tenant, User::factory()->create(['tenant_id' => $tenant->id]), $fixture, ['deadline_at' => now()->subSecond()]);
    $future = reportingAssignment($tenant, User::factory()->create(['tenant_id' => $tenant->id]), $fixture, ['deadline_at' => now()->addMinute()]);
    $completed = reportingAssignment($tenant, User::factory()->create(['tenant_id' => $tenant->id]), $fixture, ['status' => 'completed', 'completed_at' => now(), 'deadline_at' => now()->subDay()]);
    $cancelled = reportingAssignment($tenant, User::factory()->create(['tenant_id' => $tenant->id]), $fixture, ['status' => 'cancelled', 'cancelled_at' => now(), 'deadline_at' => now()->subDay()]);

    $rows = collect(app(TenantReportService::class)->getAssignmentTracking($tenant->id))->keyBy('id');

    expect($rows[$pastAssigned->id]['overdue'])->toBeTrue()
        ->and($rows[$future->id]['overdue'])->toBeFalse()
        ->and($rows[$completed->id]['overdue'])->toBeFalse()
        ->and($rows[$cancelled->id]['overdue'])->toBeFalse();
});

test('tenant report service isolates summary detail and tracking by tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
    $fixture = reportingModule();
    $assignmentA = reportingAssignment($tenantA, $userA, $fixture, ['status' => 'assigned']);
    $assignmentB = reportingAssignment($tenantB, $userB, $fixture, [
        'status' => 'completed', 'pretest_score' => 100, 'pretest_completed_at' => now(), 'completed_at' => now(),
    ]);
    reportingAttempt($assignmentB, $fixture['post'], 'submitted', 100, true);

    $service = app(TenantReportService::class);
    $summaryA = $service->getExecutiveSummary($tenantA->id);
    $trackingA = collect($service->getAssignmentTracking($tenantA->id));
    $detailA = $service->getUserDetailReport($userA);

    expect($summaryA)->total_assignments->toBe(1)->posttest_result_count->toBe(0)
        ->and($trackingA->pluck('id')->all())->toBe([$assignmentA->id])
        ->and(collect($detailA['assignments'])->pluck('id')->all())->toBe([$assignmentA->id])
        ->and(json_encode($detailA))->not->toContain('correct_index')->not->toContain('module_snapshot')->not->toContain('answers');
});

test('csv neutralizes formulas including leading whitespace and preserves shape and escaping', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $otherTenant->id, 'name' => 'Tenant B Rahasia']);
    foreach (['=2+2', '+SUM(A1:A2)', '-1+1', '@cmd', ' =2+2', "\t@cmd", 'Nama, "Baris"'."\nBaru"] as $name) {
        User::factory()->create(['tenant_id' => $tenant->id, 'name' => $name]);
    }
    User::factory()->create(['tenant_id' => $tenant->id, 'email' => "\t=2+2@example.test"]);

    $csv = app(TenantReportService::class)->exportCsv($tenant->id);

    expect($csv)
        ->toContain("'=2+2")
        ->toContain("'+SUM(A1:A2)")
        ->toContain("'-1+1")
        ->toContain("'@cmd")
        ->toContain("' =2+2")
        ->toContain("'\t@cmd")
        ->toContain("'\t=2+2@example.test")
        ->toContain('"Nama, ""Baris""'."\n".'Baru"')
        ->not->toContain('Tenant B Rahasia')
        ->not->toContain("\n=2+2,")
        ->not->toContain("\n+SUM(A1:A2),")
        ->not->toContain("\n-1+1,")
        ->not->toContain("\n@cmd,");

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $csv);
    rewind($stream);
    $rows = [];
    while (($row = fgetcsv($stream, escape: '')) !== false) {
        $rows[] = $row;
    }
    fclose($stream);
    $width = count($rows[0]);
    expect($width)->toBe(13)
        ->and(collect($rows)->every(fn (array $row): bool => count($row) === $width))->toBeTrue();
});

test('learner super admin and inactive tenant admin cannot access any tenant report endpoint', function () {
    $tenant = Tenant::factory()->create();
    $learner = User::factory()->create(['tenant_id' => $tenant->id]);
    $superAdmin = User::factory()->superAdmin()->create(['tenant_id' => null]);
    $inactiveAdmin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id, 'is_active' => false]);

    $subject = User::factory()->create(['tenant_id' => $tenant->id]);
    foreach ([$learner, $superAdmin, $inactiveAdmin] as $actor) {
        $this->actingAs($actor)->get(route('tenant.reports'))->assertForbidden();
        $this->actingAs($actor)->get(route('tenant.reports.export'))->assertForbidden();
        $this->actingAs($actor)->get(route('tenant.reports.users.show', $subject))->assertForbidden();
    }
});

test('tenant report user detail rejects cross tenant idor without leaking report data', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenantA->id]);
    $learner = User::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Rahasia Tenant B']);

    $response = $this->actingAs($admin)->get(route('tenant.reports.users.show', $learner));

    $response->assertForbidden();
    expect($response->getContent())->not->toContain('Rahasia Tenant B');
});

test('authorized csv export records metadata without exported pii', function () {
    $package = Package::create([
        'name' => 'Reporting '.uniqid(),
        'slug' => 'reporting-'.uniqid(),
        'price_monthly' => 100,
        'max_users' => 100,
        'features' => ['training', 'reports_export'],
        'includes_all_modules' => true,
        'is_active' => true,
    ]);
    $tenant = Tenant::factory()->create();
    $tenant->subscriptions()->update(['status' => 'ended', 'ends_at' => now()]);
    Subscription::create([
        'tenant_id' => $tenant->id,
        'package_id' => $package->id,
        'status' => 'active',
        'started_at' => now(),
    ]);
    $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'PII Tidak Masuk Audit']);

    $this->actingAs($admin)->get(route('tenant.reports.export'))->assertOk();

    $audit = AuditLog::query()->where('action', 'report.exported')->latest('created_at')->firstOrFail();
    expect($audit->tenant_id)->toBe($tenant->id)
        ->and($audit->properties)->toMatchArray(['tenant_id' => $tenant->id, 'format' => 'csv', 'row_count' => 2])
        ->and(json_encode($audit->properties))->not->toContain('PII Tidak Masuk Audit');
});
