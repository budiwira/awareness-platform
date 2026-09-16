<?php

namespace App\Services\Reporting;

use App\Models\Package;
use App\Models\PhishingCampaign;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlatformAnalyticsService
{
    /**
     * Get platform-wide analytics summary
     */
    public function getPlatformSummary(): array
    {
        $totalTenants = Tenant::count();

        // Active tenants: have active subscription
        $activeTenants = Tenant::whereHas('subscriptions', function ($q) {
            $q->where('status', 'active');
        })->count();

        // Total users across all tenants
        $totalUsers = User::whereNull('deleted_at')->count();

        // Average platform awareness score
        return [
            'total_tenants' => $totalTenants,
            'active_tenants' => $activeTenants,
            'total_users' => $totalUsers,
            'avg_platform_awareness_score' => $this->getPlatformAwarenessAverage(),
        ];
    }

    public function getPlatformAwarenessAverage(): float
    {
        $average = DB::table('module_assignments')
            ->where('status', 'completed')
            ->whereNotNull('score')
            ->avg('score') ?? 0;

        return round((float) $average, 1);
    }

    /**
     * @return array<int, array{id: string, name: string, users: int, assignments: int, completion_rate: float, avg_awareness: float}>
     */
    public function getTenantReportRows(): array
    {
        $userCounts = DB::table('users')
            ->select('tenant_id')
            ->selectRaw('COUNT(*) AS users_count')
            ->whereNotNull('tenant_id')
            ->whereNull('deleted_at')
            ->groupBy('tenant_id');

        $assignmentStats = DB::table('module_assignments')
            ->select('tenant_id')
            ->selectRaw('COUNT(*) AS assignments_count')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count")
            ->selectRaw("AVG(CASE WHEN status = 'completed' AND score IS NOT NULL THEN score END) AS avg_awareness")
            ->groupBy('tenant_id');

        return DB::table('tenants')
            ->leftJoinSub($userCounts, 'user_counts', 'user_counts.tenant_id', '=', 'tenants.id')
            ->leftJoinSub($assignmentStats, 'assignment_stats', 'assignment_stats.tenant_id', '=', 'tenants.id')
            ->orderBy('tenants.name')
            ->get([
                'tenants.id',
                'tenants.name',
                DB::raw('COALESCE(user_counts.users_count, 0) AS users'),
                DB::raw('COALESCE(assignment_stats.assignments_count, 0) AS assignments'),
                DB::raw('COALESCE(assignment_stats.completed_count, 0) AS completed'),
                DB::raw('COALESCE(assignment_stats.avg_awareness, 0) AS avg_awareness'),
            ])
            ->map(function ($tenant): array {
                $assignments = (int) $tenant->assignments;
                $completed = (int) $tenant->completed;

                return [
                    'id' => (string) $tenant->id,
                    'name' => (string) $tenant->name,
                    'users' => (int) $tenant->users,
                    'assignments' => $assignments,
                    'completion_rate' => $assignments > 0 ? round(($completed / $assignments) * 100, 1) : 0.0,
                    'avg_awareness' => round((float) $tenant->avg_awareness, 1),
                ];
            })
            ->all();
    }

    /**
     * Get top tenants by risk
     */
    public function getTopTenantsByRisk(): array
    {
        $tenants = Tenant::withCount('users')
            ->with('subscriptions.Package')
            ->get();

        $riskData = [];

        foreach ($tenants as $tenant) {
            $totalAssignments = DB::table('module_assignments')
                ->where('tenant_id', $tenant->id)
                ->count();

            $completedAssignments = DB::table('module_assignments')
                ->where('tenant_id', $tenant->id)
                ->where('status', 'completed')
                ->count();

            $completionRate = $totalAssignments > 0
                ? round(($completedAssignments / $totalAssignments) * 100, 1)
                : 0;

            $phishingClicked = DB::table('phishing_targets')
                ->whereIn('campaign_id', function ($q) use ($tenant) {
                    $q->select('id')
                        ->from('phishing_campaigns')
                        ->where('tenant_id', $tenant->id);
                })
                ->whereNotNull('clicked_at')
                ->count();

            $avgScore = DB::table('module_assignments')
                ->where('tenant_id', $tenant->id)
                ->where('status', 'completed')
                ->whereNotNull('score')
                ->avg('score') ?? 0;

            // Risk score (lower completion + lower score + more phishing clicks = higher risk)
            $riskScore = (100 - $completionRate) + (100 - $avgScore) + ($phishingClicked * 5);

            $currentSubscription = $tenant->currentSubscription();

            $riskData[] = [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'users_count' => $tenant->users_count,
                'completion_rate' => $completionRate,
                'avg_score' => round($avgScore, 1),
                'phishing_clicked' => $phishingClicked,
                'risk_score' => round($riskScore, 1),
                'current_plan' => $currentSubscription->Package->name ?? 'No Package',
            ];
        }

        // Sort by risk score descending
        usort($riskData, function ($a, $b) {
            return $b['risk_score'] <=> $a['risk_score'];
        });

        return array_slice($riskData, 0, 5);
    }

    /**
     * Get Package distribution
     */
    public function getPlanDistribution(): array
    {
        $packages = Package::withCount(['subscriptions' => function ($q) {
            $q->where('status', 'active');
        }])->get();

        $distribution = [];
        foreach ($packages as $Package) {
            $distribution[] = [
                'plan_name' => $Package->name,
                'plan_slug' => $Package->slug,
                'tenant_count' => $Package->subscriptions_count,
            ];
        }

        return $distribution;
    }

    /**
     * Get phishing adoption metrics
     */
    public function getPhishingAdoption(): array
    {
        $tenantsWithPhishing = Tenant::whereHas('subscriptions.Package', function ($q) {
            $q->where('is_active', true)
                ->whereJsonContains('features', 'phishing');
        })->count();

        $tenantsActivelySending = Tenant::whereHas('subscriptions.Package', function ($q) {
            $q->where('is_active', true)
                ->whereJsonContains('features', 'phishing');
        })
            ->whereHas('users', function ($q) {
                $q->whereHas('phishingCampaignsCreated');
            })
            ->count();

        $totalCampaigns = PhishingCampaign::count();

        return [
            'tenants_with_phishing_feature' => $tenantsWithPhishing,
            'tenants_actively_sending' => $tenantsActivelySending,
            'total_campaigns_sent' => $totalCampaigns,
        ];
    }
}
