<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\BillingRequestResolved;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BillingRequestController extends Controller
{
    public function index()
    {
        $requests = PlanRequest::with(['tenant', 'plan', 'requestedBy', 'resolvedBy'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (PlanRequest $r) => [
                'id' => $r->id,
                'tenant_name' => $r->tenant->name,
                'tenant_id' => $r->tenant_id,
                'plan_name' => $r->plan->name,
                'plan_slug' => $r->plan->slug,
                'note' => $r->note,
                'status' => $r->status,
                'requested_by' => $r->requestedBy->name,
                'requested_at' => $r->created_at->format('d M Y'),
                'resolved_by' => $r->resolvedBy?->name,
                'resolved_at' => $r->resolved_at?->format('d M Y'),
            ]);

        return Inertia::render('Platform/Billing/Requests', [
            'requests' => $requests,
        ]);
    }

    public function approve(Request $request)
    {
        $validated = $request->validate([
            'request_id' => ['required', 'exists:plan_requests,id'],
        ]);

        $planRequest = PlanRequest::with(['tenant', 'plan'])->findOrFail($validated['request_id']);

        if ($planRequest->status !== 'pending') {
            return redirect()->back()->withErrors(['request_id' => 'Request sudah diproses sebelumnya.']);
        }

        $tenant = $planRequest->tenant;
        $plan = $planRequest->plan;

        // Guard downgrade: cek jumlah user aktif
        $activeUserCount = User::where('tenant_id', $tenant->id)->whereNull('deleted_at')->count();

        if ($plan->max_users < $activeUserCount) {
            return redirect()->back()->withErrors([
                'request_id' => "Plan '{$plan->name}' maksimal {$plan->max_users} users, tenant '{$tenant->name}' punya {$activeUserCount} active users. Tidak bisa approve.",
            ]);
        }

        DB::transaction(function () use ($tenant, $plan, $planRequest, $request) {
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

            // Update request
            $planRequest->update([
                'status' => 'approved',
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
            ]);

            // Notifikasi semua tenant_admin tenant tersebut
            $tenantAdmins = User::where('tenant_id', $tenant->id)
                ->where('role', 'tenant_admin')
                ->whereNull('deleted_at')
                ->get();

            foreach ($tenantAdmins as $admin) {
                $admin->notify(new BillingRequestResolved($planRequest, 'approved'));
            }

            // Audit log
            Audit::log('billing.request_approved', $planRequest, [
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'request_id' => $planRequest->id,
            ]);
        });

        return redirect()->route('platform.billing.requests')->with('success', 'Request berhasil di-approve.');
    }

    public function reject(Request $request)
    {
        $validated = $request->validate([
            'request_id' => ['required', 'exists:plan_requests,id'],
        ]);

        $planRequest = PlanRequest::with(['tenant', 'plan'])->findOrFail($validated['request_id']);

        if ($planRequest->status !== 'pending') {
            return redirect()->back()->withErrors(['request_id' => 'Request sudah diproses sebelumnya.']);
        }

        DB::transaction(function () use ($planRequest, $request) {
            $planRequest->update([
                'status' => 'rejected',
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
            ]);

            // Notifikasi tenant_admin
            $tenantAdmins = User::where('tenant_id', $planRequest->tenant_id)
                ->where('role', 'tenant_admin')
                ->whereNull('deleted_at')
                ->get();

            foreach ($tenantAdmins as $admin) {
                $admin->notify(new BillingRequestResolved($planRequest, 'rejected'));
            }

            Audit::log('billing.request_rejected', $planRequest, [
                'tenant_id' => $planRequest->tenant_id,
                'request_id' => $planRequest->id,
            ]);
        });

        return redirect()->route('platform.billing.requests')->with('success', 'Request berhasil di-reject.');
    }
}
