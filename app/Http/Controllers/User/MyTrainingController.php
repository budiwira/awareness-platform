<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ModuleAssignment;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MyTrainingController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        // User hanya bisa melihat assignment miliknya sendiri
        $assignments = ModuleAssignment::with(['module:id,title,description,duration_minutes'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('User/MyTraining/Index', [
            'assignments' => $assignments,
        ]);
    }

    public function markComplete(Request $request, ModuleAssignment $assignment)
    {
        // Pastikan assignment ini milik user yang login
        if ($assignment->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak berhak mengubah assignment ini.');
        }

        // Hanya bisa menandai selesai, tidak bisa mengubah kembali
        if ($assignment->status === 'completed') {
            return redirect()->back();
        }

        $assignment->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        Audit::log('training.completed', $assignment, [
            'module_id' => $assignment->training_module_id,
        ]);

        return redirect()->route('user.training.index');
    }

    public function show(ModuleAssignment $assignment)
    {
        // Pastikan assignment ini milik user yang login
        if ($assignment->user_id !== auth()->id()) {
            abort(403, 'Anda tidak berhak melihat assignment ini.');
        }

        $assignment->load(['module:id,title,description,content,duration_minutes']);

        return Inertia::render('User/MyTraining/Show', [
            'assignment' => $assignment,
        ]);
    }
}