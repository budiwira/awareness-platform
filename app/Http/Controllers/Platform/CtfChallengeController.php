<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\CtfChallenge;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CtfChallengeController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', CtfChallenge::class);

        $challenges = CtfChallenge::withCount('solves')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($challenge) {
                return [
                    'id' => $challenge->id,
                    'title' => $challenge->title,
                    'description' => $challenge->description,
                    'category' => $challenge->category,
                    'difficulty' => $challenge->difficulty,
                    'points' => $challenge->points,
                    'status' => $challenge->status,
                    'is_active' => $challenge->is_active,
                    'solves_count' => $challenge->solves_count,
                    'created_at' => $challenge->created_at,
                ];
            });

        return Inertia::render('Platform/Ctf/Index', ['challenges' => $challenges]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', CtfChallenge::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'in:general,phishing,password,social_engineering,web'],
            'difficulty' => ['required', 'in:beginner,intermediate,advanced'],
            'points' => ['required', 'integer', 'min:10', 'max:1000'],
            'flag' => ['required', 'string', 'min:4', 'max:255'],
            'hint' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:draft,published'],
        ]);

        $challenge = CtfChallenge::create($validated);
        Audit::log('ctf.created', $challenge, ['title' => $challenge->title]);

        return redirect()->route('platform.ctf.index');
    }

    public function publish(CtfChallenge $challenge)
    {
        Gate::authorize('update', $challenge);

        $challenge->status = 'published';
        $challenge->save();

        $challenge->refresh();
        if ($challenge->status !== 'published') {
            return back()->withErrors(['action' => 'Status gagal diperbarui (RLS/permission).']);
        }

        Audit::log('ctf.published', $challenge, ['title' => $challenge->title]);

        return back();
    }

    public function archive(CtfChallenge $challenge)
    {
        Gate::authorize('update', $challenge);

        $challenge->status = 'archived';
        $challenge->save();

        $challenge->refresh();
        if ($challenge->status !== 'archived') {
            return back()->withErrors(['action' => 'Status gagal diperbarui (RLS/permission).']);
        }

        Audit::log('ctf.archived', $challenge, ['title' => $challenge->title]);

        return back();
    }
}
