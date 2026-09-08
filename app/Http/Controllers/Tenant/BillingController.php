<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantEntitlement;
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
        $entitlement = app(TenantEntitlement::class);
        $features = $entitlement->getEntitledFeatures($tenant);
        $moduleIds = $entitlement->getEntitledModuleIds($tenant);
        $package = $current->package ?? Package::where('slug', 'starter')->first();

        return Inertia::render('Tenant/Billing/Index', [
            'packages' => Package::where('is_active', true)->orderBy('price_monthly')->get(),
            'current_plan' => $package,
            'current_subscription' => $current,
            'entitlements' => [
                'features' => $features,
                'module_info' => $package?->includes_all_modules ? 'Semua modul published' : count($moduleIds).' modul kurasi',
                'module_ids' => $moduleIds,
            ],
            'user_count' => User::where('tenant_id', $tenant->id)->count(),
            'requests' => PackageRequest::where('tenant_id', $tenant->id)
                ->with(['Package', 'requestedBy', 'resolvedBy'])
                ->orderBy('created_at', 'desc')
                ->get(),
        ]);
    }

    public function subscribe(Request $request)
    {
        // Kunci route ini untuk super_admin saja
        if ($request->user()->role->value !== 'super_admin') {
            abort(403, 'Tenant admin tidak bisa subscribe langsung. Gunakan request Package.');
        }

        $validated = $request->validate([
            'package_id' => ['required', 'exists:packages,id'],
            'tenant_id' => ['required', 'exists:tenants,id'], // Super admin perlu specify tenant
        ]);

        $tenant = Tenant::findOrFail($validated['tenant_id']);
        $package = Package::findOrFail($validated['package_id']);

        // Aturan bisnis: tidak bisa memilih Package di bawah jumlah user saat ini
        $userCount = User::where('tenant_id', $tenant->id)->count();

        if ($package->max_users < $userCount) {
            return redirect()->back()->withErrors([
                'package_id' => "Package '{$package->name}' maks. {$package->max_users} users, Anda punya {$userCount} users.",
            ]);
        }

        DB::transaction(function () use ($tenant, $package) {
            Subscription::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled', 'ends_at' => now()]);

            Subscription::create([
                'tenant_id' => $tenant->id,
                'package_id' => $package->id,
                'status' => 'active',
                'started_at' => now(),
            ]);
        });

        Audit::log('billing.package_changed', $package, ['tenant_id' => $tenant->id, 'Package' => $package->slug]);

        return redirect()->route('tenant.billing.index')->with('success', 'Package berhasil diperbarui.');
    }

    public function requestPackageChange(Request $request)
    {
        $validated = $request->validate([
            'package_id' => ['required', 'exists:packages,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $tenant = $request->user()->tenant;

        PackageRequest::create([
            'tenant_id' => $tenant->id,
            'package_id' => $validated['package_id'],
            'note' => $validated['note'] ?? null,
            'status' => 'pending',
            'requested_by' => $request->user()->id,
        ]);

        Audit::log('billing.package_requested', null, [
            'tenant_id' => $tenant->id,
            'package_id' => $validated['package_id'],
        ]);

        return redirect()->route('tenant.billing.index')->with('success', 'Permintaan perubahan Package berhasil diajukan.');
    }
}
