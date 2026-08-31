<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CaseParticipation;
use App\Models\CtfChallenge;
use App\Models\CtfSolve;
use App\Models\ModuleAssignment;
use App\Models\QuizAttempt;
use App\Models\TtxScore;
use App\Support\Scoring\AwarenessScore;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MyScoreController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $assignments = ModuleAssignment::with('module:id,title')
            ->where('user_id', $userId)->orderBy('created_at', 'desc')->get();

        $attempts = QuizAttempt::with('quiz:id,title')
            ->where('user_id', $userId)->orderBy('created_at', 'desc')->get();

        $cases = CaseParticipation::where('user_id', $userId)->get();
        $solves = CtfSolve::where('user_id', $userId)->get();
        $ttx = TtxScore::where('user_id', $userId)->get();

        $totalCtfPoints = (int) CtfChallenge::where('is_active', true)->sum('points');
        $tenant = $request->user()->tenant;
        $entitlement = $tenant ? app(\App\Services\TenantEntitlement::class)->getEntitledFeatures($tenant) : null;

        $score = (new AwarenessScore)->compute($assignments, $attempts, $cases, $solves, $ttx, $totalCtfPoints, $entitlement);

        $stats = [
            'assigned' => $assignments->count(),
            'completed' => $assignments->where('status', 'completed')->count(),
            'attempts' => $attempts->count(),
            'avg_score' => (int) round($attempts->avg('score') ?? 0),
        ];

        return Inertia::render('User/MyScore/Index', [
            'score' => $score,
            'stats' => $stats,
            'assignments' => $assignments,
            'attempts' => $attempts,
        ]);
    }
}