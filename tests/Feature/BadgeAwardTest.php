<?php

use App\Models\Badge;
use App\Models\ModuleAssignment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Tenant;
use App\Models\TrainingModule;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\BadgeAwardService;
use App\Support\Tenant\CurrentTenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    // Set super admin context untuk fixture creation
    DB::statement("SELECT set_config('app.role', 'super_admin', false)");

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'user',
    ]);

    $this->badgeService = app(BadgeAwardService::class);
});

test('user earns first module completed badge', function () {
    // Seed badges
    $this->artisan('db:seed', ['--class' => 'BadgeSeeder']);

    $badge = Badge::where('criteria_type', 'first_module_completed')->first();
    expect($badge)->not->toBeNull();

    // Create module assignment
    DB::statement("SELECT set_config('app.tenant_id', '{$this->tenant->id}', false)");

    $module = TrainingModule::create([
        'title' => 'Test Module',
        'content' => 'Content',
        'duration_minutes' => 10,
        'status' => 'published',
        'is_active' => true,
    ]);

    $assignment = ModuleAssignment::create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'training_module_id' => $module->id,
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    // Check badge earned
    $this->badgeService->checkModuleCompletion($this->user);

    $earned = UserBadge::where('user_id', $this->user->id)
        ->where('badge_id', $badge->id)
        ->exists();

    expect($earned)->toBeTrue();
});

test('user earns perfect score badge', function () {
    $this->artisan('db:seed', ['--class' => 'BadgeSeeder']);

    $badge = Badge::where('criteria_type', 'quiz_perfect_score')->first();

    DB::statement("SELECT set_config('app.tenant_id', '{$this->tenant->id}', false)");

    // Create training module first
    $module = TrainingModule::create([
        'title' => 'Test Module',
        'content' => 'Content',
        'duration_minutes' => 10,
        'status' => 'published',
        'is_active' => true,
    ]);

    $quiz = Quiz::create([
        'training_module_id' => $module->id,
        'title' => 'Test Quiz',
        'passing_score' => 70,
        'duration_minutes' => 30,
        'is_active' => true,
    ]);

    QuizAttempt::create([
        'quiz_id' => $quiz->id,
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => 'submitted',
        'score' => 100,
        'passed' => true,
        'started_at' => now(),
        'submitted_at' => now(),
        'answers' => [],
    ]);

    $this->badgeService->checkQuizPerformance($this->user);

    $earned = UserBadge::where('user_id', $this->user->id)
        ->where('badge_id', $badge->id)
        ->exists();

    expect($earned)->toBeTrue();
});

test('user earns streak badge after 7 days login', function () {
    $this->artisan('db:seed', ['--class' => 'BadgeSeeder']);

    $badge = Badge::where('criteria_type', 'streak_days')
        ->where('criteria_value', 7)
        ->first();

    $this->user->login_streak = 7;
    $this->user->save();

    $this->badgeService->checkStreak($this->user);

    $earned = UserBadge::where('user_id', $this->user->id)
        ->where('badge_id', $badge->id)
        ->exists();

    expect($earned)->toBeTrue();
});

test('badge is not awarded twice', function () {
    $this->artisan('db:seed', ['--class' => 'BadgeSeeder']);

    $badge = Badge::where('criteria_type', 'first_module_completed')->first();

    // Award badge pertama kali
    DB::statement("SELECT set_config('app.tenant_id', '{$this->tenant->id}', false)");
    $this->badgeService->awardBadge($this->user, $badge);

    // Coba award lagi
    $this->badgeService->checkAndAward($this->user, 'first_module_completed', 1);

    // Seharusnya tetap 1
    $count = UserBadge::where('user_id', $this->user->id)
        ->where('badge_id', $badge->id)
        ->count();

    expect($count)->toBe(1);
});

test('BadgeAward restores caller TenantContext after success or failure', function (string $outcome, string $role) {
    $this->artisan('db:seed', ['--class' => 'BadgeSeeder']);
    $badge = Badge::where('criteria_type', 'first_module_completed')->firstOrFail();
    $caller = User::factory()->create();
    $previousTenant = $role === 'tenant_admin' ? $caller->tenant_id : null;
    DB::statement("SELECT set_config('app.tenant_id', ?, false), set_config('app.user_id', ?, false), set_config('app.role', ?, false)", [
        $previousTenant ?? '', (string) $caller->id, $role,
    ]);
    app(CurrentTenant::class)->set($previousTenant);

    $dbRole = DB::selectOne('SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user');
    expect($dbRole->rolsuper)->toBeFalse()->and($dbRole->rolbypassrls)->toBeFalse();
    DB::statement('ALTER TABLE user_badges FORCE ROW LEVEL SECURITY');
    $before = (array) DB::selectOne("SELECT current_setting('app.tenant_id', true) AS tenant_id, current_setting('app.user_id', true) AS user_id, current_setting('app.role', true) AS role");
    $transactionLevel = DB::transactionLevel();

    if ($outcome === 'database failure') {
        $this->badgeService->awardBadge($this->user, $badge);
    }

    $failure = new RuntimeException('Badge listener failed');
    Event::listen('eloquent.creating: '.UserBadge::class, function () use ($outcome, $failure) {
        expect(DB::selectOne("SELECT current_setting('app.tenant_id', true) AS id")->id)->toBe($this->tenant->id)
            ->and(app(CurrentTenant::class)->id())->toBe($this->tenant->id);
        if ($outcome === 'listener failure') {
            throw $failure;
        }
    });

    try {
        $this->badgeService->awardBadge($this->user, $badge);
        expect($outcome)->toBe('success');
    } catch (QueryException $exception) {
        expect($outcome)->toBe('database failure')
            ->and($exception->errorInfo[0])->toBe('23505');
    } catch (RuntimeException $exception) {
        expect($outcome)->toBe('listener failure')->and($exception)->toBe($failure);
    }

    expect((array) DB::selectOne("SELECT current_setting('app.tenant_id', true) AS tenant_id, current_setting('app.user_id', true) AS user_id, current_setting('app.role', true) AS role"))->toBe($before)
        ->and(app(CurrentTenant::class)->id())->toBe($previousTenant)
        ->and(DB::transactionLevel())->toBe($transactionLevel);
})->with(['success', 'listener failure', 'database failure'])->with(['tenant_admin', 'super_admin', '']);
