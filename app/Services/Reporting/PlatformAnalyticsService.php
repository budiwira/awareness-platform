<?php

namespace App\Services\Reporting;

use App\Models\PhishingCampaign;
use App\Models\Plan;
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
        $avgPlatformScore = DB::table('module_assignments')
            ->where('status', 'completed')
            ->whereNotNull('score')
            ->avg('score') ?? 0;
        
        return [
            'total_tenants' => $totalTenants,
            'active_tenants' => $activeTenants,
            'total_users' => $totalUsers,
            'avg_platform_awareness_score' => round($avgPlatformScore, 1),
        ];
    }
    
    /**
     * Get top tenants by risk
     */
    public function getTopTenantsByRisk(): array
    {
        $tenants = Tenant::withCount('users')
            ->with('subscriptions.plan')
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
                'current_plan' => $currentSubscription?->plan?->name ?? 'No Plan',
            ];
        }
        
        // Sort by risk score descending
        usort($riskData, function ($a, $b) {
            return $b['risk_score'] <=> $a['risk_score'];
        });
        
        return array_slice($riskData, 0, 5);
    }
    
    /**
     * Get plan distribution
     */
    public function getPlanDistribution(): array
    {
        $plans = Plan::withCount(['subscriptions' => function ($q) {
            $q->where('status', 'active');
        }])->get();
        
        $distribution = [];
        foreach ($plans as $plan) {
            $distribution[] = [
                'plan_name' => $plan->name,
                'plan_slug' => $plan->slug,
                'tenant_count' => $plan->subscriptions_count,
            ];
        }
        
        return $distribution;
    }
    
    /**
     * Get phishing adoption metrics
     */
    public function getPhishingAdoption(): array
    {
        $tenantsWithPhishing = Tenant::whereHas('subscriptions.plan', function ($q) {
            $q->where('is_active', true)
                ->whereJsonContains('features', 'phishing');
        })->count();
        
        $tenantsActivelySending = Tenant::whereHas('subscriptions.plan', function ($q) {
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
