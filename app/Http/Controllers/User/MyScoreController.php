<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ModuleAssignment;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MyScoreController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $assignments = ModuleAssignment::with('module:id,title')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        $attempts = QuizAttempt::with('quiz:id,title')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = [
            'assigned' => $assignments->count(),
            'completed' => $assignments->where('status', 'completed')->count(),
            'attempts' => $attempts->count(),
            'avg_score' => (int) round($attempts->avg('score') ?? 0),
        ];

        return Inertia::render('User/MyScore/Index', [
            'stats' => $stats,
            'assignments' => $assignments,
            'attempts' => $attempts,
        ]);
    }
}