<?php

namespace App\Http\Controllers;

use App\Services\Reporting\PlatformAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlatformReportController extends Controller
{
    public function __construct(
        private PlatformAnalyticsService $analyticsService
    ) {}

    public function index(Request $request)
    {
        $summary = $this->analyticsService->getPlatformSummary();
        $topTenants = $this->analyticsService->getTopTenantsByRisk();
        $planDistribution = $this->analyticsService->getPlanDistribution();
        $phishingAdoption = $this->analyticsService->getPhishingAdoption();

        return Inertia::render('Platform/Dashboard', [
            'summary' => $summary,
            'top_tenants_by_risk' => $topTenants,
            'plan_distribution' => $planDistribution,
            'phishing_adoption' => $phishingAdoption,
        ]);
    }
}
