<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BillingRequestResolved;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::withCount('users')->orderBy('name')->get();
        $plans = Plan::where('is_active', true)->orderBy('price_monthly')->get();

        return Inertia::render('Platform/Tenants/Index', [
            'tenants' => $tenants,
            'plans' => $plans,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        // Slug unik otomatis — server-side, tidak bisa diinjeksi client
        $slug = Str::slug($validated['name']);
        $base = $slug;
        $n = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'status' => 'active',
        ]);

        Audit::log('tenant.created', $tenant, ['slug' => $slug]);

        return redirect()->route('platform.tenants.index');
    }

    public function setPlan(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'exists:tenants,id'],
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $tenant = Tenant::findOrFail($validated['tenant_id']);
        $plan = Plan::findOrFail($validated['plan_id']);

        // Guard downgrade: cek jumlah user aktif
        $activeUserCount = User::where('tenant_id', $tenant->id)->whereNull('deleted_at')->count();

        if ($plan->max_users < $activeUserCount) {
            return redirect()->back()->withErrors([
                'plan_id' => "Plan '{$plan->name}' maksimal {$plan->max_users} users, tenant '{$tenant->name}' punya {$activeUserCount} active users.",
            ]);
        }

        DB::transaction(function () use ($tenant, $plan, $request) {
            // Batalkan subscription aktif lama
            Subscription::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled', 'ends_at' => now()]);

            // Buat subscription baru
            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => now(),
            ]);

            // Notifikasi tenant_admin
            $tenantAdmins = User::where('tenant_id', $tenant->id)
                ->where('role', 'tenant_admin')
                ->whereNull('deleted_at')
                ->get();

            foreach ($tenantAdmins as $admin) {
                $admin->notify(new BillingRequestResolved(null, 'approved', $plan));
            }

            // Audit log
            Audit::log('billing.plan_set_by_admin', $plan, [
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
            ]);
        });

        return redirect()->route('platform.tenants.index')->with('success', 'Plan berhasil diubah.');
    }
}

