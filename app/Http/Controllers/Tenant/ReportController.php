<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ModuleAssignment;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $users = User::where('tenant_id', $tenantId)->get();

        $assignments = ModuleAssignment::with('user:id,name,email')
            ->where('tenant_id', $tenantId)
            ->get();

        $attempts = QuizAttempt::where('tenant_id', $tenantId)->get();

        $completed = $assignments->where('status', 'completed')->count();
        $passedAttempts = $attempts->where('passed', true)->count();

        $stats = [
            'users' => $users->count(),
            'assignments' => $assignments->count(),
            'completion_rate' => $assignments->count() > 0
                ? (int) round($completed / $assignments->count() * 100)
                : 0,
            'avg_score' => (int) round($attempts->avg('score') ?? 0),
            'pass_rate' => $attempts->count() > 0
                ? (int) round($passedAttempts / $attempts->count() * 100)
                : 0,
        ];

        // Progres per user — explainable, bukan black-box
        $perUser = $users->map(function ($u) use ($assignments, $attempts) {
            $uAssignments = $assignments->where('user_id', $u->id);
            $uAttempts = $attempts->where('user_id', $u->id);

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'assigned' => $uAssignments->count(),
                'completed' => $uAssignments->where('status', 'completed')->count(),
                'attempts' => $uAttempts->count(),
                'avg_score' => (int) round($uAttempts->avg('score') ?? 0),
            ];
        })->values();

        return Inertia::render('Tenant/Reports/Index', [
            'stats' => $stats,
            'per_user' => $perUser,
        ]);
    }
}