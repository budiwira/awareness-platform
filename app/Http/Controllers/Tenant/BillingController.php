<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Plan;
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

        return Inertia::render('Tenant/Billing/Index', [
            'plans' => Plan::where('is_active', true)->orderBy('price_monthly')->get(),
            'current_plan' => $current?->plan ?? Plan::where('slug', 'free')->first(),
            'current_subscription' => $current,
            'user_count' => User::where('tenant_id', $tenant->id)->count(),
        ]);
    }

    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $tenant = $request->user()->tenant;
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
}