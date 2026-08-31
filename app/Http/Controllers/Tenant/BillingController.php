<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;

        $current = $tenant->currentSubscription();
        $entitlement = app(\App\Services\TenantEntitlement::class);
        $features = $entitlement->getEntitledFeatures($tenant);
        $moduleIds = $entitlement->getEntitledModuleIds($tenant);
        $plan = $current?->plan ?? Plan::where('slug', 'starter')->first();

        return Inertia::render('Tenant/Billing/Index', [
            'plans' => Plan::where('is_active', true)->orderBy('price_monthly')->get(),
            'current_plan' => $plan,
            'current_subscription' => $current,
            'entitlements' => [
                'features' => $features,
                'module_info' => $plan?->includes_all_modules ? 'Semua modul published' : count($moduleIds) . ' modul kurasi',
                'module_ids' => $moduleIds,
            ],
            'user_count' => User::where('tenant_id', $tenant->id)->count(),
            'requests' => PlanRequest::where('tenant_id', $tenant->id)
                ->with(['plan', 'requestedBy', 'resolvedBy'])
                ->orderBy('created_at', 'desc')
                ->get(),
        ]);
    }

    public function subscribe(Request $request)
    {
        // Kunci route ini untuk super_admin saja
        if ($request->user()->role->value !== 'super_admin') {
            abort(403, 'Tenant admin tidak bisa subscribe langsung. Gunakan request plan.');
        }

        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'tenant_id' => ['required', 'exists:tenants,id'], // Super admin perlu specify tenant
        ]);

        $tenant = \App\Models\Tenant::findOrFail($validated['tenant_id']);
        $plan = Plan::findOrFail($validated['plan_id']);

        // Aturan bisnis: tidak bisa memilih plan di bawah jumlah user saat ini
        $userCount = User::where('tenant_id', $tenant->id)->count();

        if ($plan->max_users < $userCount) {
            return redirect()->back()->withErrors([
                'plan_id' => "Plan '{$plan->name}' maks. {$plan->max_users} users, Anda punya {$userCount} users.",
            ]);
        }

        DB::transaction(function () use ($tenant, $plan) {
            Subscription::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled', 'ends_at' => now()]);

            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => now(),
            ]);
        });

        Audit::log('billing.plan_changed', $plan, ['tenant_id' => $tenant->id, 'plan' => $plan->slug]);

        return redirect()->route('tenant.billing.index')->with('success', 'Plan berhasil diperbarui.');
    }

    public function requestPlanChange(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $tenant = $request->user()->tenant;

        PlanRequest::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $validated['plan_id'],
            'note' => $validated['note'] ?? null,
            'status' => 'pending',
            'requested_by' => $request->user()->id,
        ]);

        Audit::log('billing.plan_requested', null, [
            'tenant_id' => $tenant->id,
            'plan_id' => $validated['plan_id'],
        ]);

        return redirect()->route('tenant.billing.index')->with('success', 'Permintaan perubahan plan berhasil diajukan.');
    }
}