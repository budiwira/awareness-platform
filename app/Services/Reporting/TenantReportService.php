<?php

namespace App\Services\Reporting;

use App\Models\ModuleAssignment;
use App\Models\PhishingTarget;
use App\Models\QuizAttempt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenantReportService
{
    /**
     * Get executive summary for a tenant
     */
    public function getExecutiveSummary(string $tenantId): array
    {
        $assignments = ModuleAssignment::where('tenant_id', $tenantId);
        $quizAttempts = QuizAttempt::where('tenant_id', $tenantId)
            ->where('status', 'submitted');
        $phishingTargets = PhishingTarget::whereHas('campaign', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        });
        
        $totalAssignments = $assignments->count();
        $completedAssignments = $assignments->where('status', 'completed')->count();
        $completionRate = $totalAssignments > 0 
            ? round(($completedAssignments / $totalAssignments) * 100, 1) 
            : 0;
        
        $avgQuizScore = $quizAttempts->avg('score') ?? 0;
        
        $totalPhishingTargets = $phishingTargets->count();
        $clickedPhishing = $phishingTargets->whereNotNull('clicked_at')->count();
        $phishingClickRate = $totalPhishingTargets > 0
            ? round(($clickedPhishing / $totalPhishingTargets) * 100, 1)
            : 0;
        
        // Average awareness score (dari ModuleAssignment)
        $avgAwarenessScore = $assignments->where('status', 'completed')
            ->whereNotNull('score')
            ->avg('score') ?? 0;
        
        // Users at risk: completion < 50% OR quiz score < 60 OR phishing clicked
        $usersAtRisk = User::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                // Low completion
                $q->whereHas('assignments', function ($subQ) {
                    $subQ->where('status', '!=', 'completed');
                }, '>=', 2)
                // OR low quiz score
                ->orWhereHas('quizAttempts', function ($subQ) {
                    $subQ->where('status', 'submitted')
                        ->where('score', '<', 60);
                })
                // OR clicked phishing
                ->orWhereHas('phishingTargets', function ($subQ) {
                    $subQ->whereNotNull('clicked_at');
                });
            })
            ->count();
        
        return [
            'avg_awareness_score' => round($avgAwarenessScore, 1),
            'completion_rate' => $completionRate,
            'avg_quiz_score' => round($avgQuizScore, 1),
            'phishing_click_rate' => $phishingClickRate,
            'users_at_risk' => $usersAtRisk,
        ];
    }
    
    /**
     * Get 30-day trend data
     */
    public function getTrendData(string $tenantId): array
    {
        $days = [];
        $completionTrend = [];
        $quizScoreTrend = [];
        $phishingClickTrend = [];
        
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $days[] = $date;
            
            // Completion rate up to that day
            $totalAssignments = ModuleAssignment::where('tenant_id', $tenantId)
                ->whereDate('created_at', '<=', $date)
                ->count();
            $completed = ModuleAssignment::where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereDate('completed_at', '<=', $date)
                ->count();
            $completionTrend[] = $totalAssignments > 0 ? round(($completed / $totalAssignments) * 100, 1) : 0;
            
            // Average quiz score up to that day
            $avgScore = QuizAttempt::where('tenant_id', $tenantId)
                ->where('status', 'submitted')
                ->whereDate('submitted_at', '<=', $date)
                ->avg('score');
            $quizScoreTrend[] = $avgScore ? round($avgScore, 1) : 0;
            
            // Phishing click rate up to that day
            $totalTargets = PhishingTarget::whereHas('campaign', function ($q) use ($tenantId, $date) {
                $q->where('tenant_id', $tenantId)
                    ->whereDate('created_at', '<=', $date);
            })->count();
            $clicked = PhishingTarget::whereHas('campaign', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
            ->whereNotNull('clicked_at')
            ->whereDate('clicked_at', '<=', $date)
            ->count();
            $phishingClickTrend[] = $totalTargets > 0 ? round(($clicked / $totalTargets) * 100, 1) : 0;
        }
        
        return [
            'days' => $days,
            'completion_trend' => $completionTrend,
            'quiz_score_trend' => $quizScoreTrend,
            'phishing_click_trend' => $phishingClickTrend,
        ];
    }
    
    /**
     * Get risk tier breakdown
     */
    public function getRiskTierBreakdown(string $tenantId): array
    {
        $users = User::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->with(['assignments', 'quizAttempts', 'phishingTargets'])
            ->get();
        
        $tiers = [
            'baik' => 0,
            'cukup' => 0,
            'perlu_perbaikan' => 0,
            'belum_mengerjakan' => 0,
        ];
        
        foreach ($users as $user) {
            $tier = $this->calculateUserTier($user);
            $tiers[$tier]++;
        }
        
        return $tiers;
    }
    
    /**
     * Get detailed user risk list
     */
    public function getUserRiskList(string $tenantId): array
    {
        $users = User::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->with(['assignments', 'quizAttempts', 'phishingTargets'])
            ->get();
        
        $result = [];
        foreach ($users as $user) {
            $totalAssignments = $user->assignments->count();
            $completedAssignments = $user->assignments->where('status', 'completed')->count();
            $completionRate = $totalAssignments > 0 
                ? round(($completedAssignments / $totalAssignments) * 100, 1) 
                : 0;
            
            $avgScore = $user->assignments->where('status', 'completed')
                ->whereNotNull('score')
                ->avg('score') ?? 0;
            
            $phishingClicked = $user->phishingTargets->whereNotNull('clicked_at')->count();
            
            $result[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'awareness_score' => round($avgScore, 1),
                'completion_rate' => $completionRate,
                'phishing_clicked' => $phishingClicked,
                'tier' => $this->calculateUserTier($user),
            ];
        }
        
        return $result;
    }
    
    /**
     * Get user detail report
     */
    public function getUserDetailReport(User $user): array
    {
        $assignments = ModuleAssignment::where('user_id', $user->id)
            ->with('module')
            ->get();
        
        $quizAttempts = QuizAttempt::where('user_id', $user->id)
            ->with('quiz')
            ->orderBy('submitted_at', 'desc')
            ->get();
        
        $phishingHistory = PhishingTarget::where('user_id', $user->id)
            ->with('campaign')
            ->orderBy('created_at', 'desc')
            ->get();
        
        $totalAssignments = $assignments->count();
        $completedAssignments = $assignments->where('status', 'completed')->count();
        $completionRate = $totalAssignments > 0 
            ? round(($completedAssignments / $totalAssignments) * 100, 1) 
            : 0;
        
        $avgScore = $assignments->where('status', 'completed')
            ->whereNotNull('score')
            ->avg('score') ?? 0;
        
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
            ],
            'summary' => [
                'total_assignments' => $totalAssignments,
                'completed_assignments' => $completedAssignments,
                'completion_rate' => $completionRate,
                'avg_awareness_score' => round($avgScore, 1),
                'phishing_received' => $phishingHistory->count(),
                'phishing_clicked' => $phishingHistory->whereNotNull('clicked_at')->count(),
                'tier' => $this->calculateUserTier($user),
            ],
            'assignments' => $assignments->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'module_title' => $assignment->module->title ?? 'N/A',
                    'status' => $assignment->status,
                    'score' => $assignment->score,
                    'completed_at' => $assignment->completed_at?->format('d M Y'),
                ];
            }),
            'quiz_attempts' => $quizAttempts->map(function ($attempt) {
                return [
                    'id' => $attempt->id,
                    'quiz_title' => $attempt->quiz->title ?? 'N/A',
                    'score' => $attempt->score,
                    'passed' => $attempt->passed,
                    'submitted_at' => $attempt->submitted_at?->format('d M Y H:i'),
                ];
            }),
            'phishing_history' => $phishingHistory->map(function ($target) {
                return [
                    'id' => $target->id,
                    'campaign_title' => $target->campaign->title ?? 'N/A',
                    'status' => $target->status,
                    'clicked_at' => $target->clicked_at?->format('d M Y H:i'),
                ];
            }),
        ];
    }
    
    /**
     * Export CSV data
     */
    public function exportCsv(string $tenantId): string
    {
        $users = User::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->with(['assignments', 'quizAttempts', 'phishingTargets'])
            ->get();
        
        $csv = "user_name,email,awareness_score,completion_rate,quiz_score,phishing_received,phishing_clicked,risk_tier\n";
        
        foreach ($users as $user) {
            $totalAssignments = $user->assignments->count();
            $completedAssignments = $user->assignments->where('status', 'completed')->count();
            $completionRate = $totalAssignments > 0 
                ? round(($completedAssignments / $totalAssignments) * 100, 1) 
                : 0;
            
            $avgScore = $user->assignments->where('status', 'completed')
                ->whereNotNull('score')
                ->avg('score') ?? 0;
            
            $avgQuizScore = $user->quizAttempts->where('status', 'submitted')
                ->avg('score') ?? 0;
            
            $phishingReceived = $user->phishingTargets->count();
            $phishingClicked = $user->phishingTargets->whereNotNull('clicked_at')->count();
            
            $tier = $this->calculateUserTier($user);
            
            $csv .= sprintf(
                "%s,%s,%.1f,%.1f,%.1f,%d,%d,%s\n",
                $this->escapeCsv($user->name),
                $this->escapeCsv($user->email),
                round($avgScore, 1),
                $completionRate,
                round($avgQuizScore, 1),
                $phishingReceived,
                $phishingClicked,
                $tier
            );
        }
        
        return $csv;
    }
    
    /**
     * Calculate user risk tier
     */
    private function calculateUserTier(User $user): string
    {
        $totalAssignments = $user->assignments->count();
        $completedAssignments = $user->assignments->where('status', 'completed')->count();
        
        if ($totalAssignments === 0) {
            return 'belum_mengerjakan';
        }
        
        $completionRate = ($completedAssignments / $totalAssignments) * 100;
        $avgScore = $user->assignments->where('status', 'completed')
            ->whereNotNull('score')
            ->avg('score') ?? 0;
        $phishingClicked = $user->phishingTargets->whereNotNull('clicked_at')->count();
        
        // Tier logic
        if ($completionRate >= 80 && $avgScore >= 80 && $phishingClicked === 0) {
            return 'baik';
        } elseif ($completionRate >= 50 && $avgScore >= 60) {
            return 'cukup';
        } elseif ($completionRate > 0) {
            return 'perlu_perbaikan';
        } else {
            return 'belum_mengerjakan';
        }
    }
    
    /**
     * Escape CSV field
     */
    private function escapeCsv(string $value): string
    {
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
