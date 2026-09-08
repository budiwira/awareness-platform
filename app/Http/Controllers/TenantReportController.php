<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Reporting\TenantReportService;
use App\Services\TenantEntitlement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantReportController extends Controller
{
    public function __construct(
        private TenantReportService $reportService,
        private TenantEntitlement $entitlement
    ) {}

    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;

        $summary = $this->reportService->getExecutiveSummary($tenant->id);
        $trend = $this->reportService->getTrendData($tenant->id);
        $riskTiers = $this->reportService->getRiskTierBreakdown($tenant->id);
        $users = $this->reportService->getUserRiskList($tenant->id);

        $canExport = $this->entitlement->hasFeature($tenant, 'reports_export');

        return Inertia::render('Tenant/Reports/Index', [
            'summary' => $summary,
            'trend' => $trend,
            'risk_tiers' => $riskTiers,
            'users' => $users,
            'can_export' => $canExport,
        ]);
    }

    public function export(Request $request)
    {
        $tenant = $request->user()->tenant;

        // Gate: reports_export feature
        if (! $this->entitlement->hasFeature($tenant, 'reports_export')) {
            return Inertia::render('Shared/FeatureLocked', [
                'feature' => 'Export Reports',
                'message' => 'Fitur export laporan tidak tersedia di Package Anda.',
                'cta' => 'Upgrade ke Package Pro atau Enterprise untuk mengakses fitur ini.',
            ]);
        }

        $csv = $this->reportService->exportCsv($tenant->id);

        $filename = sprintf(
            'awareness-report-%s-%s.csv',
            $tenant->slug,
            now()->format('Y-m-d')
        );

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function showUser(Request $request, User $user)
    {
        // RLS check: tenant admin can only view users from their own tenant
        if ($user->tenant_id !== $request->user()->tenant_id) {
            abort(403, 'Unauthorized access to user from another tenant.');
        }

        $report = $this->reportService->getUserDetailReport($user);

        return Inertia::render('Tenant/UserDetailReport', [
            'report' => $report,
        ]);
    }
}
