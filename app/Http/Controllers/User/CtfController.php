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