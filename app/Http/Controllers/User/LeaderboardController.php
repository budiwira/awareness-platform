<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Support\Scoring\AwarenessScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LeaderboardController extends Controller
{
    public function __construct(
        private AwarenessScore $awarenessScore
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $tenant = $user->tenant;

        // Ambil semua user dalam tenant (hanya yang show_on_leaderboard = true)
        $users = $tenant->users()
            ->where('is_active', true)
            ->where('show_on_leaderboard', true)
            ->get();

        // Hitung awareness score untuk setiap user
        $leaderboard = $users->map(function ($u) use ($tenant) {
            $assignments = $u->moduleAssignments;
            $quizAttempts = $u->quizAttempts;
            $caseParticipations = $u->caseParticipations;
            $ctfSolves = $u->ctfSolves;
            $ttxScores = $u->ttxScores;
            $phishingTargets = $u->phishingTargets;

            // Get total CTF points for this tenant
            $totalCtfPoints = DB::table('ctf_challenges')
                ->where('is_active', true)
                ->sum('points');

            // Get tenant entitled features
            $subscription = $tenant->currentSubscription();
            $entitledFeatures = $subscription?->plan?->features;

            $scoreData = $this->awarenessScore->compute(
                $assignments,
                $quizAttempts,
                $caseParticipations,
                $ctfSolves,
                $ttxScores,
                $totalCtfPoints,
                $entitledFeatures,
                $phishingTargets
            );

            return [
                'id' => $u->id,
                'name' => $u->name,
                'avatar_path' => $u->avatar_path,
                'score' => $scoreData['overall'],
            ];
        })->sortByDesc('score')->values();

        // Ambil top 20
        $top20 = $leaderboard->take(20);

        // Cari posisi current user
        $currentUserPosition = null;
        $currentUserData = null;

        foreach ($leaderboard as $index => $entry) {
            if ($entry['id'] === $user->id) {
                $currentUserPosition = $index + 1;
                $currentUserData = $entry;
                break;
            }
        }

        return Inertia::render('User/Leaderboard', [
            'leaderboard' => $top20,
            'currentUserPosition' => $currentUserPosition,
            'currentUserData' => $currentUserData,
            'totalParticipants' => $leaderboard->count(),
        ]);
    }
}
