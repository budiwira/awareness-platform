<?php

namespace App\Services\Reporting;

use App\Models\ModuleAssignment;
use App\Models\PhishingTarget;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\AssessmentLifecycle;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class TenantReportService
{
    /** @return array<string, int|float|null> */
    public function getExecutiveSummary(string $tenantId): array
    {
        $assignments = $this->assignmentCycles($tenantId);
        $metrics = $this->moduleMetrics($assignments);
        $avgQuizScore = QuizAttempt::query()->where('tenant_id', $tenantId)->where('status', 'submitted')->avg('score');
        $totalPhishingTargets = PhishingTarget::query()
            ->whereHas('campaign', fn ($query) => $query->where('tenant_id', $tenantId))->count();
        $clickedPhishing = PhishingTarget::query()
            ->whereHas('campaign', fn ($query) => $query->where('tenant_id', $tenantId))
            ->whereNotNull('clicked_at')->count();
        $avgAwarenessScore = $assignments->where('status', 'completed')->whereNotNull('score')->avg('score');

        $usersAtRisk = User::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($tenantId) {
                $query->whereHas('assignments', function ($assignments) use ($tenantId) {
                    $assignments->where('tenant_id', $tenantId)->whereNotIn('status', ['completed', 'cancelled']);
                }, '>=', 2)
                    ->orWhereHas('quizAttempts', function ($attempts) use ($tenantId) {
                        $attempts->where('tenant_id', $tenantId)->where('status', 'submitted')->where('score', '<', 60);
                    })
                    ->orWhereHas('phishingTargets', fn ($targets) => $targets
                        ->whereNotNull('clicked_at')
                        ->whereHas('campaign', fn ($campaign) => $campaign->where('tenant_id', $tenantId)));
            })->count();

        return array_merge($metrics, [
            'avg_awareness_score' => $avgAwarenessScore === null ? null : round((float) $avgAwarenessScore, 1),
            'avg_quiz_score' => $avgQuizScore === null ? null : round((float) $avgQuizScore, 1),
            'phishing_click_rate' => $totalPhishingTargets > 0 ? round(($clickedPhishing / $totalPhishingTargets) * 100, 1) : null,
            'users_at_risk' => $usersAtRisk,
        ]);
    }

    /** @return array{days: array<int, string>, completion_trend: array<int, float>, submitted_quiz_score_trend: array<int, float|null>, quiz_score_trend: array<int, float|null>, phishing_click_trend: array<int, float>} */
    public function getTrendData(string $tenantId): array
    {
        $days = [];
        $completionTrend = [];
        $submittedQuizScoreTrend = [];
        $phishingClickTrend = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $days[] = $date;
            $totalAssignments = ModuleAssignment::query()->where('tenant_id', $tenantId)
                ->where('status', '<>', 'cancelled')->whereDate('assigned_at', '<=', $date)->count();
            $completed = ModuleAssignment::query()->where('tenant_id', $tenantId)->where('status', 'completed')
                ->whereDate('assigned_at', '<=', $date)->whereDate('completed_at', '<=', $date)->count();
            $completionTrend[] = $totalAssignments > 0 ? round(($completed / $totalAssignments) * 100, 1) : 0.0;

            $avgScore = QuizAttempt::query()->where('tenant_id', $tenantId)->where('status', 'submitted')
                ->whereDate('submitted_at', '<=', $date)->avg('score');
            $submittedQuizScoreTrend[] = $avgScore === null ? null : round((float) $avgScore, 1);

            $totalTargets = PhishingTarget::query()->whereHas('campaign', function ($query) use ($tenantId, $date) {
                $query->where('tenant_id', $tenantId)->whereDate('created_at', '<=', $date);
            })->count();
            $clicked = PhishingTarget::query()->whereHas('campaign', fn ($query) => $query->where('tenant_id', $tenantId))
                ->whereNotNull('clicked_at')->whereDate('clicked_at', '<=', $date)->count();
            $phishingClickTrend[] = $totalTargets > 0 ? round(($clicked / $totalTargets) * 100, 1) : 0.0;
        }

        return [
            'days' => $days,
            'completion_trend' => $completionTrend,
            'submitted_quiz_score_trend' => $submittedQuizScoreTrend,
            'quiz_score_trend' => $submittedQuizScoreTrend,
            'phishing_click_trend' => $phishingClickTrend,
        ];
    }

    /** @return array<string, int> */
    public function getRiskTierBreakdown(string $tenantId): array
    {
        $tiers = ['baik' => 0, 'cukup' => 0, 'perlu_perbaikan' => 0, 'belum_mengerjakan' => 0];
        foreach ($this->reportUsers($tenantId) as $user) {
            $tiers[$this->calculateUserTier($user)]++;
        }

        return $tiers;
    }

    /** @return array<int, array<string, mixed>> */
    public function getUserRiskList(string $tenantId): array
    {
        return $this->reportUsers($tenantId)->map(function (User $user): array {
            $assignments = $user->assignments->where('status', '<>', 'cancelled');
            $total = $assignments->count();
            $completed = $assignments->where('status', 'completed')->count();
            $avgScore = $assignments->where('status', 'completed')->whereNotNull('score')->avg('score');

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'awareness_score' => $avgScore === null ? null : round((float) $avgScore, 1),
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0.0,
                'phishing_clicked' => $user->phishingTargets->whereNotNull('clicked_at')->count(),
                'tier' => $this->calculateUserTier($user),
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function getAssignmentTracking(string $tenantId): array
    {
        return $this->assignmentCycles($tenantId, true)->map(fn (ModuleAssignment $assignment): array => array_merge(
            $this->assignmentProjection($assignment),
            ['learner_name' => $assignment->user->name, 'learner_email' => $assignment->user->email]
        ))->values()->all();
    }

    /** @return array<string, mixed> */
    public function getUserDetailReport(User $user): array
    {
        $tenantId = (string) $user->tenant_id;
        $assignments = ModuleAssignment::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)
            ->with([
                'module:id,title',
                'quizAttempts' => fn ($query) => $query->where('tenant_id', $tenantId)
                    ->select(['id', 'module_assignment_id', 'quiz_id', 'assessment_purpose', 'status', 'score', 'passed', 'submitted_at']),
            ])->orderByDesc('assigned_at')->get();
        $quizAttempts = QuizAttempt::query()->where('tenant_id', $tenantId)->where('user_id', $user->id)
            ->whereIn('module_assignment_id', $assignments->modelKeys())->with('quiz:id,title')->orderByDesc('submitted_at')->get();
        $phishingHistory = PhishingTarget::query()->where('user_id', $user->id)
            ->whereHas('campaign', fn ($query) => $query->where('tenant_id', $tenantId))
            ->with('campaign:id,title')->orderByDesc('created_at')->get();
        $activeAssignments = $assignments->where('status', '<>', 'cancelled');
        $user->setRelation('assignments', $assignments);
        $user->setRelation('phishingTargets', $phishingHistory);

        return [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role->value],
            'summary' => array_merge($this->moduleMetrics($activeAssignments), [
                'avg_awareness_score' => $activeAssignments->where('status', 'completed')->whereNotNull('score')->avg('score'),
                'phishing_received' => $phishingHistory->count(),
                'phishing_clicked' => $phishingHistory->whereNotNull('clicked_at')->count(),
                'tier' => $this->calculateUserTier($user),
            ]),
            'assignments' => $assignments->map(fn (ModuleAssignment $assignment): array => $this->assignmentProjection($assignment))->values()->all(),
            'quiz_attempts' => $quizAttempts->map(fn (QuizAttempt $attempt): array => [
                'id' => $attempt->id,
                'assignment_id' => $attempt->module_assignment_id,
                'quiz_title' => $attempt->quiz->title ?? 'N/A',
                'purpose' => $attempt->assessment_purpose,
                'status' => $attempt->status,
                'score' => $attempt->score,
                'passed' => $attempt->passed,
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            ])->values()->all(),
            'phishing_history' => $phishingHistory->map(fn ($target): array => [
                'id' => $target->id,
                'campaign_title' => $target->campaign->title ?? 'N/A',
                'status' => $target->status,
                'clicked_at' => $target->clicked_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    public function exportCsv(string $tenantId): string
    {
        $csv = $this->csvRow([
            'user_name', 'email', 'awareness_score', 'completion_rate', 'quiz_score', 'phishing_received',
            'phishing_clicked', 'risk_tier', 'total_assignment_cycles', 'avg_baseline_score',
            'avg_best_posttest_score', 'avg_learning_gain_pp', 'posttest_pass_rate',
        ]);

        foreach ($this->reportUsers($tenantId) as $user) {
            $assignments = $user->assignments->where('status', '<>', 'cancelled');
            $metrics = $this->moduleMetrics($assignments);
            $avgScore = $assignments->where('status', 'completed')->whereNotNull('score')->avg('score');
            $avgQuizScore = $user->quizAttempts->where('status', 'submitted')->avg('score');
            $csv .= $this->csvRow([
                $this->spreadsheetSafe($user->name),
                $this->spreadsheetSafe($user->email),
                $avgScore === null ? '' : round((float) $avgScore, 1),
                $metrics['completion_rate'],
                $avgQuizScore === null ? '' : round((float) $avgQuizScore, 1),
                $user->phishingTargets->count(),
                $user->phishingTargets->whereNotNull('clicked_at')->count(),
                $this->spreadsheetSafe($this->calculateUserTier($user)),
                $metrics['total_assignments'],
                $metrics['avg_baseline_score'] ?? '',
                $metrics['avg_best_posttest_score'] ?? '',
                $metrics['avg_learning_gain'] ?? '',
                $metrics['posttest_pass_rate'] ?? '',
            ]);
        }

        return $csv;
    }

    public function countExportRows(string $tenantId): int
    {
        return User::query()->where('tenant_id', $tenantId)->whereNull('deleted_at')->count();
    }

    /** @return EloquentCollection<int, ModuleAssignment> */
    private function assignmentCycles(string $tenantId, bool $includeCancelled = false): EloquentCollection
    {
        return ModuleAssignment::query()->where('tenant_id', $tenantId)
            ->when(! $includeCancelled, fn ($query) => $query->where('status', '<>', 'cancelled'))
            ->with([
                'user' => fn ($query) => $query->withTrashed()->select(['id', 'name', 'email', 'tenant_id']),
                'module:id,title',
                'quizAttempts' => fn ($query) => $query->where('tenant_id', $tenantId)
                    ->select(['id', 'module_assignment_id', 'quiz_id', 'assessment_purpose', 'status', 'score', 'passed', 'submitted_at']),
            ])->orderByDesc('assigned_at')->get();
    }

    /** @param Collection<int, ModuleAssignment> $assignments
     * @return array<string, int|float|null>
     */
    private function moduleMetrics(Collection $assignments): array
    {
        $rows = $assignments->map(fn (ModuleAssignment $assignment): array => $this->assignmentProjection($assignment));
        $baselines = $rows->pluck('pretest_baseline')->filter(fn ($score) => $score !== null);
        $posttests = $rows->pluck('best_posttest')->filter(fn ($score) => $score !== null);
        $gains = $rows->pluck('learning_gain')->filter(fn ($score) => $score !== null);
        $participating = $rows->where('posttest_participated', true);
        $total = $rows->count();
        $completed = $rows->where('status', 'completed')->count();
        $passed = $participating->where('posttest_passed', true)->count();

        return [
            'total_assignments' => $total,
            'completed_assignments' => $completed,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0.0,
            'overdue_count' => $rows->where('overdue', true)->count(),
            'overdue_assignments' => $rows->where('overdue', true)->count(),
            'avg_baseline_score' => $baselines->isEmpty() ? null : round((float) $baselines->avg(), 1),
            'baseline_count' => $baselines->count(),
            'avg_best_posttest_score' => $posttests->isEmpty() ? null : round((float) $posttests->avg(), 1),
            'posttest_result_count' => $posttests->count(),
            'avg_learning_gain' => $gains->isEmpty() ? null : round((float) $gains->avg(), 1),
            'gain_count' => $gains->count(),
            'posttest_pass_rate' => $participating->isEmpty() ? null : round(($passed / $participating->count()) * 100, 1),
            'posttest_participation_count' => $participating->count(),
            'posttest_passed_count' => $passed,
        ];
    }

    /** @return array<string, mixed> */
    private function assignmentProjection(ModuleAssignment $assignment): array
    {
        $posttestAttempts = $assignment->quizAttempts->where('quiz_id', $assignment->posttest_quiz_id)
            ->where('assessment_purpose', 'posttest');
        $terminal = $posttestAttempts->whereIn('status', ['submitted', 'expired']);
        $valid = $posttestAttempts->where('status', 'submitted')
            ->filter(fn (QuizAttempt $attempt): bool => $attempt->score !== null);
        $best = $valid->isEmpty() ? null : (int) $valid->max('score');
        $terminalPretest = $assignment->quizAttempts->where('quiz_id', $assignment->pretest_quiz_id)
            ->where('assessment_purpose', 'pretest')
            ->whereIn('status', ['submitted', 'expired']);
        $hasBaseline = $assignment->pretest_quiz_id !== null
            && $assignment->pretest_completed_at !== null
            && $assignment->pretest_score !== null
            && $terminalPretest->isNotEmpty();
        $baseline = $hasBaseline ? (int) $assignment->pretest_score : null;
        $gain = $baseline !== null && $best !== null ? $best - $baseline : null;
        $passed = $valid->contains(fn (QuizAttempt $attempt): bool => $attempt->passed === true);
        $attemptsExhausted = $assignment->posttest_quiz_id !== null
            && $terminal->count() >= AssessmentLifecycle::POSTTEST_MAX_ATTEMPTS && ! $passed;

        return [
            'id' => $assignment->id,
            'cycle_id' => $assignment->id,
            'module_title' => $assignment->module->title ?? 'N/A',
            'assigned_at' => $assignment->assigned_at?->toIso8601String(),
            'deadline_at' => $assignment->deadline_at?->toIso8601String(),
            'status' => $assignment->status,
            'display_status' => match (true) {
                $assignment->status === 'cancelled' => 'cancelled',
                $assignment->status === 'completed' => 'completed',
                $assignment->isOverdue() => 'overdue',
                $attemptsExhausted => 'needs_follow_up',
                default => $assignment->status,
            },
            'overdue' => $assignment->isOverdue(),
            'pretest_baseline' => $baseline,
            'best_posttest' => $best,
            'learning_gain' => $gain,
            'posttest_attempts_used' => $terminal->count(),
            'posttest_configured' => $assignment->posttest_quiz_id !== null,
            'posttest_participated' => $assignment->posttest_quiz_id !== null && $terminal->isNotEmpty(),
            'posttest_passed' => $passed,
            'attempts_exhausted' => $attemptsExhausted,
            'completed_at' => $assignment->completed_at?->toIso8601String(),
        ];
    }

    /** @return EloquentCollection<int, User> */
    private function reportUsers(string $tenantId): EloquentCollection
    {
        return User::query()->where('tenant_id', $tenantId)->whereNull('deleted_at')->with([
            'assignments' => fn ($query) => $query->where('tenant_id', $tenantId)
                ->with([
                    'module:id,title',
                    'quizAttempts' => fn ($attempts) => $attempts->where('tenant_id', $tenantId)
                        ->select(['id', 'module_assignment_id', 'quiz_id', 'assessment_purpose', 'status', 'score', 'passed', 'submitted_at']),
                ]),
            'quizAttempts' => fn ($query) => $query->where('tenant_id', $tenantId),
            'phishingTargets' => fn ($query) => $query
                ->whereHas('campaign', fn ($campaign) => $campaign->where('tenant_id', $tenantId))
                ->with('campaign'),
        ])->get();
    }

    private function calculateUserTier(User $user): string
    {
        $assignments = $user->assignments->where('status', '<>', 'cancelled');
        $total = $assignments->count();
        if ($total === 0) {
            return 'belum_mengerjakan';
        }

        $completionRate = ($assignments->where('status', 'completed')->count() / $total) * 100;
        $avgScore = $assignments->where('status', 'completed')->whereNotNull('score')->avg('score') ?? 0;
        $phishingClicked = $user->phishingTargets->whereNotNull('clicked_at')->count();

        return match (true) {
            $completionRate >= 80 && $avgScore >= 80 && $phishingClicked === 0 => 'baik',
            $completionRate >= 50 && $avgScore >= 60 => 'cukup',
            $completionRate > 0 => 'perlu_perbaikan',
            default => 'belum_mengerjakan',
        };
    }

    private function spreadsheetSafe(string $value): string
    {
        // Preserve the original text while forcing spreadsheet parsers to treat
        // formula markers, including those hidden behind leading whitespace, as text.
        return preg_match('/^[\s\p{Z}]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }

    /** @param array<int, int|float|string> $values */
    private function csvRow(array $values): string
    {
        return implode(',', array_map(function (int|float|string $value): string {
            $text = (string) $value;

            return strpbrk($text, ",\"\r\n") === false
                ? $text
                : '"'.str_replace('"', '""', $text).'"';
        }, $values))."\n";
    }
}
