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
            ->get();

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
        ]);

        $challenge = CtfChallenge::create($validated);
        Audit::log('ctf.created', $challenge, ['title' => $challenge->title]);

        return redirect()->route('platform.ctf.index');
    }
}