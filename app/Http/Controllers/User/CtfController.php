<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CtfChallenge;
use App\Models\CtfSolve;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CtfController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        $entitlement = app(\App\Services\TenantEntitlement::class);

        if (!$tenant || !$entitlement->hasFeature($tenant, 'ctf')) {
            return Inertia::render('Shared/FeatureLocked', [
                'title' => 'Fitur CTF Terkunci',
                'message' => 'Organisasi Anda belum mengaktifkan fitur Capture The Flag.',
                'cta' => 'Hubungi admin organisasi untuk upgrade',
            ])->toResponse(request())->setStatusCode(403);
        }

        $solves = CtfSolve::where('user_id', $request->user()->id)
            ->get()
            ->keyBy('challenge_id');

        $challenges = CtfChallenge::where('is_active', true)
            ->where('status', 'published')
            ->orderBy('points')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'description' => $c->description,
                'category' => $c->category,
                'difficulty' => $c->difficulty,
                'points' => $c->points,
                'hint' => $c->hint,
                'solved' => $solves->has($c->id),
                'solved_points' => $solves->get($c->id)?->points,
            ]);

        return Inertia::render('User/Ctf/Index', ['challenges' => $challenges]);
    }

    public function submit(Request $request, CtfChallenge $challenge)
    {
        $tenant = $request->user()->tenant;
        $entitlement = app(\App\Services\TenantEntitlement::class);

        if (!$tenant || !$entitlement->hasFeature($tenant, 'ctf')) {
            abort(403, 'Organisasi Anda belum mengaktifkan fitur CTF.');
        }

        if (! $challenge->is_active || $challenge->status !== 'published') {
            return redirect()->route('user.ctf.index');
        }

        $validated = $request->validate([
            'flag' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $already = CtfSolve::where('user_id', $user->id)
            ->where('challenge_id', $challenge->id)
            ->exists();

        if ($already) {
            return redirect()->route('user.ctf.index');
        }

        // Verifikasi server-side: trim + case-insensitive
        $correct = strcasecmp(trim($validated['flag']), trim($challenge->flag)) === 0;

        if (! $correct) {
            Audit::log('ctf.failed_attempt', $challenge);

            return redirect()->back()->withErrors(['flag' => 'Flag salah. Coba lagi.']);
        }

        CtfSolve::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id, // server-side
            'challenge_id' => $challenge->id,
            'points' => $challenge->points,
            'solved_at' => now(),
        ]);

        Audit::log('ctf.solved', $challenge, ['points' => $challenge->points]);

        return redirect()->route('user.ctf.index');
    }
}