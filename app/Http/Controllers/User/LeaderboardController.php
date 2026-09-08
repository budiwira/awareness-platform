<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        // Eager load relasi untuk hindari N+1 saat compute AwarenessScore per user
        $users = $tenant->users()
            ->where('is_active', true)
            ->where('show_on_leaderboard', true)
            ->with([
                'moduleAssignments',
                'quizAttempts',
                'caseParticipations',
                'ctfSolves',
                'ttxScores',
                'phishingTargets',
            ])
            ->get();

        // Hoist queries yang tidak tergantung per-user keluar dari loop
        $totalCtfPoints = DB::table('ctf_challenges')
            ->where('is_active', true)
            ->sum('points');
        $subscription = $tenant->currentSubscription();
        $entitledFeatures = $subscription?->package?->features;

        // Hitung awareness score untuk setiap user
        $leaderboard = $users->map(function (User $u) use ($totalCtfPoints, $entitledFeatures) {
            $assignments = $u->moduleAssignments;
            $quizAttempts = $u->quizAttempts;
            $caseParticipations = $u->caseParticipations;
            $ctfSolves = $u->ctfSolves;
            $ttxScores = $u->ttxScores;
            $phishingTargets = $u->phishingTargets;

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
