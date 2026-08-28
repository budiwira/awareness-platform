<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CaseParticipation;
use App\Models\CtfChallenge;
use App\Models\CtfSolve;
use App\Models\ModuleAssignment;
use App\Models\QuizAttempt;
use App\Models\TtxScore;
use App\Models\User;
use App\Support\Scoring\AwarenessScore;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $report = $this->buildReport($request->user()->tenant_id);

        return Inertia::render('Tenant/Reports/Index', $report);
    }

    public function export(Request $request)
    {
        $report = $this->buildReport($request->user()->tenant_id);
        $perUser = $report['per_user'];

        $filename = 'awareness-report-' . now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($perUser) {
            $out = fopen('php://output', 'w');

            // BOM agar Excel membuka UTF-8 dengan benar
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Nama', 'Email', 'Ditugaskan', 'Selesai', 'Percobaan', 'Rata-rata Quiz', 'Awareness Score']);

            foreach ($perUser as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['email'],
                    $row['assigned'],
                    $row['completed'],
                    $row['attempts'],
                    $row['avg_score'],
                    $row['awareness_score'],
                ]);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function buildReport(string $tenantId): array
    {
        $users = User::where('tenant_id', $tenantId)->get();

        $assignments = ModuleAssignment::with('user:id,name,email')->where('tenant_id', $tenantId)->get();
        $attempts = QuizAttempt::where('tenant_id', $tenantId)->get();

        $totalCtfPoints = (int) CtfChallenge::where('is_active', true)->sum('points');

        $gAssign = ModuleAssignment::where('tenant_id', $tenantId)->get()->groupBy('user_id');
        $gQuiz = QuizAttempt::where('tenant_id', $tenantId)->get()->groupBy('user_id');
        $gCase = CaseParticipation::where('tenant_id', $tenantId)->get()->groupBy('user_id');
        $gSolve = CtfSolve::where('tenant_id', $tenantId)->get()->groupBy('user_id');
        $gTtx = TtxScore::where('tenant_id', $tenantId)->get()->groupBy('user_id');

        $scorer = new AwarenessScore;

        $completed = $assignments->where('status', 'completed')->count();
        $passedAttempts = $attempts->where('passed', true)->count();

        $perUser = $users->map(function ($u) use ($assignments, $attempts, $scorer, $gAssign, $gQuiz, $gCase, $gSolve, $gTtx, $totalCtfPoints) {
            $uAssign = $assignments->where('user_id', $u->id);
            $uAttempts = $attempts->where('user_id', $u->id);

            $awareness = $scorer->compute(
                $gAssign->get($u->id, collect()),
                $gQuiz->get($u->id, collect()),
                $gCase->get($u->id, collect()),
                $gSolve->get($u->id, collect()),
                $gTtx->get($u->id, collect()),
                $totalCtfPoints
            );

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'assigned' => $uAssign->count(),
                'completed' => $uAssign->where('status', 'completed')->count(),
                'attempts' => $uAttempts->count(),
                'avg_score' => (int) round($uAttempts->avg('score') ?? 0),
                'awareness_score' => $awareness['overall'],
            ];
        })->values();

        $orgAvg = (int) round(collect($perUser)->avg('awareness_score') ?? 0);

        $stats = [
            'users' => $users->count(),
            'assignments' => $assignments->count(),
            'completion_rate' => $assignments->count() > 0 ? (int) round($completed / $assignments->count() * 100) : 0,
            'avg_score' => (int) round($attempts->avg('score') ?? 0),
            'pass_rate' => $attempts->count() > 0 ? (int) round($passedAttempts / $attempts->count() * 100) : 0,
            'org_avg' => $orgAvg,
        ];

        return ['stats' => $stats, 'per_user' => $perUser];
    }
}