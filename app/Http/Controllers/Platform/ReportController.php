<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\CaseParticipation;
use App\Models\CtfChallenge;
use App\Models\CtfSolve;
use App\Models\ModuleAssignment;
use App\Models\QuizAttempt;
use App\Models\Tenant;
use App\Models\TtxScore;
use App\Models\User;
use App\Support\Scoring\AwarenessScore;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index()
    {
        $tenants = Tenant::orderBy('name')->get();
        $totalCtfPoints = (int) CtfChallenge::where('is_active', true)->sum('points');
        $scorer = new AwarenessScore;

        $rows = $tenants->map(function ($t) use ($scorer, $totalCtfPoints) {
            $users = User::where('tenant_id', $t->id)->get();
            $assignments = ModuleAssignment::where('tenant_id', $t->id)->get();
            $attempts = QuizAttempt::where('tenant_id', $t->id)->get();

            $gAssign = $assignments->groupBy('user_id');
            $gQuiz = $attempts->groupBy('user_id');
            $gCase = CaseParticipation::where('tenant_id', $t->id)->get()->groupBy('user_id');
            $gSolve = CtfSolve::where('tenant_id', $t->id)->get()->groupBy('user_id');
            $gTtx = TtxScore::where('tenant_id', $t->id)->get()->groupBy('user_id');

            $scores = $users->map(fn ($u) => $scorer->compute(
                $gAssign->get($u->id, collect()),
                $gQuiz->get($u->id, collect()),
                $gCase->get($u->id, collect()),
                $gSolve->get($u->id, collect()),
                $gTtx->get($u->id, collect()),
                $totalCtfPoints
            )['overall']);

            $completed = $assignments->where('status', 'completed')->count();

            return [
                'id' => $t->id,
                'name' => $t->name,
                'users' => $users->count(),
                'assignments' => $assignments->count(),
                'completion_rate' => $assignments->count() > 0 ? (int) round($completed / $assignments->count() * 100) : 0,
                'avg_awareness' => (int) round($scores->avg() ?? 0),
            ];
        });

        $platformAvg = (int) round(collect($rows)->avg('avg_awareness') ?? 0);

        return Inertia::render('Platform/Reports/Index', [
            'rows' => $rows,
            'platform_avg' => $platformAvg,
        ]);
    }
}