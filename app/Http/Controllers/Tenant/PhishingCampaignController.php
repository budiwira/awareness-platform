<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Mail\PhishingSimMail;
use App\Models\PhishingCampaign;
use App\Models\PhishingTarget;
use App\Models\User;
use App\Services\TenantEntitlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PhishingCampaignController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        $entitlement = app(TenantEntitlement::class);

        if (! $tenant || ! $entitlement->hasFeature($tenant, 'phishing')) {
            return Inertia::render('Shared/FeatureLocked', [
                'title' => 'Fitur Simulasi Phishing Terkunci',
                'message' => 'Organisasi Anda belum mengaktifkan fitur Simulasi Phishing.',
                'cta' => 'Hubungi admin organisasi untuk upgrade',
            ])->toResponse(request())->setStatusCode(403);
        }

        $campaigns = PhishingCampaign::where('tenant_id', $tenant->id)
            ->withCount('targets')
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Tenant/Phishing/Index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function create(Request $request)
    {
        $tenant = $request->user()->tenant;
        $entitlement = app(TenantEntitlement::class);

        if (! $tenant || ! $entitlement->hasFeature($tenant, 'phishing')) {
            return Inertia::render('Shared/FeatureLocked', [
                'title' => 'Fitur Simulasi Phishing Terkunci',
                'message' => 'Organisasi Anda belum mengaktifkan fitur Simulasi Phishing.',
                'cta' => 'Hubungi admin organisasi untuk upgrade',
            ])->toResponse(request())->setStatusCode(403);
        }

        $users = User::where('tenant_id', $tenant->id)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return Inertia::render('Tenant/Phishing/Create', [
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        $entitlement = app(TenantEntitlement::class);

        if (! $tenant || ! $entitlement->hasFeature($tenant, 'phishing')) {
            abort(403, 'Fitur tidak tersedia');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'sender_name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body_template' => 'required|string',
            'target_user_ids' => 'required|array|min:1',
            'target_user_ids.*' => 'exists:users,id',
        ]);

        $campaign = PhishingCampaign::create([
            'tenant_id' => $tenant->id,
            'title' => $validated['title'],
            'sender_name' => $validated['sender_name'],
            'subject' => $validated['subject'],
            'body_template' => $validated['body_template'],
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        // Create targets
        foreach ($validated['target_user_ids'] as $userId) {
            PhishingTarget::create([
                'campaign_id' => $campaign->id,
                'user_id' => $userId,
                'token' => Str::random(64),
                'status' => 'sent',
            ]);
        }

        return redirect()->route('tenant.phishing.show', $campaign->id)
            ->with('success', 'Kampanye berhasil dibuat');
    }

    public function show(Request $request, PhishingCampaign $campaign)
    {
        $tenant = $request->user()->tenant;
        $entitlement = app(TenantEntitlement::class);

        if (! $tenant || ! $entitlement->hasFeature($tenant, 'phishing')) {
            return Inertia::render('Shared/FeatureLocked', [
                'title' => 'Fitur Simulasi Phishing Terkunci',
                'message' => 'Organisasi Anda belum mengaktifkan fitur Simulasi Phishing.',
                'cta' => 'Hubungi admin organisasi untuk upgrade',
            ])->toResponse(request())->setStatusCode(403);
        }

        if ($campaign->tenant_id !== $tenant->id) {
            abort(403);
        }

        $targets = $campaign->targets()
            ->with('user:id,name,email')
            ->orderBy('clicked_at', 'desc')
            ->orderBy('created_at')
            ->get()
            ->map(fn (PhishingTarget $t, int $key) => [
                'id' => $t->id,
                'user_name' => $t->user->name,
                'user_email' => $t->user->email,
                'status' => $t->status,
                'clicked_at' => $t->clicked_at?->toIso8601String(),
            ]);

        $stats = [
            'total' => $targets->count(),
            'clicked' => $targets->where('status', 'clicked')->count(),
            'click_rate' => $targets->count() > 0
                ? round($targets->where('status', 'clicked')->count() / $targets->count() * 100, 1)
                : 0,
        ];

        return Inertia::render('Tenant/Phishing/Show', [
            'campaign' => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'sender_name' => $campaign->sender_name,
                'subject' => $campaign->subject,
                'body_template' => $campaign->body_template,
                'status' => $campaign->status,
                'email_snapshot' => $campaign->email_snapshot,
                'created_at' => $campaign->created_at->toIso8601String(),
            ],
            'targets' => $targets,
            'stats' => $stats,
        ]);
    }

    public function send(Request $request, PhishingCampaign $campaign)
    {
        $tenant = $request->user()->tenant;
        $entitlement = app(TenantEntitlement::class);

        if (! $tenant || ! $entitlement->hasFeature($tenant, 'phishing')) {
            abort(403, 'Fitur tidak tersedia');
        }

        if ($campaign->tenant_id !== $tenant->id) {
            abort(403);
        }

        if ($campaign->status !== 'draft') {
            return back()->withErrors(['status' => 'Kampanye sudah dikirim']);
        }

        // Save email snapshot
        $campaign->update([
            'status' => 'running',
            'email_snapshot' => [
                'subject' => $campaign->subject,
                'body' => $campaign->body_template,
            ],
        ]);

        // Send emails
        $targets = $campaign->targets;
        foreach ($targets as $target) {
            Mail::to($target->user->email)->send(
                new PhishingSimMail(
                    $target,
                    $campaign->sender_name,
                    $campaign->subject,
                    $campaign->body_template
                )
            );
        }

        return back()->with('success', 'Email simulasi berhasil dikirim');
    }
}
