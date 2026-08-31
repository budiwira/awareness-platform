<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ModuleAssignment;
use App\Models\QuizAttempt;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ModuleQuizController extends Controller
{
    public function show(Request $request, ModuleAssignment $assignment)
    {
        $this->ensureOwner($request, $assignment);

        $tenant = $request->user()->tenant;
        $entitlement = app(\App\Services\TenantEntitlement::class);
        if (!$tenant || !$entitlement->hasModule($tenant, $assignment->training_module_id)) {
            return \Inertia\Inertia::render('Shared/FeatureLocked', [
                'title' => 'Modul Terkunci',
                'message' => 'Organisasi Anda belum mengaktifkan modul ini.',
                'cta' => 'Hubungi admin organisasi',
            ])->toResponse($request)->setStatusCode(403);
        }

        $quiz = $assignment->module?->quiz;

        if (! $quiz || ! $quiz->is_active) {
            return redirect()->route('user.training.index')->withErrors(['quiz' => 'Quiz belum tersedia untuk modul ini.']);
        }

        // KEAMANAN: hanya id, pertanyaan, dan pilihan yang dikirim.
        // correct_index TIDAK PERNAH meninggalkan server.
        $questions = $quiz->questions->map(fn ($q) => [
            'id' => $q->id,
            'question' => $q->question,
            'options' => $q->options,
        ])->values();

        return Inertia::render('User/MyTraining/Quiz', [
            'assignment' => ['id' => $assignment->id, 'module_title' => $assignment->module->title],
            'questions' => $questions,
            'passing_score' => $quiz->passing_score,
        ]);
    }

    public function submit(Request $request, ModuleAssignment $assignment)
    {
        $this->ensureOwner($request, $assignment);

        $tenant = $request->user()->tenant;
        $entitlement = app(\App\Services\TenantEntitlement::class);
        if (!$tenant || !$entitlement->hasModule($tenant, $assignment->training_module_id)) {
            abort(403, 'Organisasi Anda belum mengaktifkan modul ini.');
        }

        $quiz = $assignment->module?->quiz;

        if (! $quiz || ! $quiz->is_active) {
            return redirect()->route('user.training.index');
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        $questions = $quiz->questions;

        if ($questions->count() === 0) {
            return redirect()->route('user.training.index')->withErrors(['quiz' => 'Quiz belum memiliki pertanyaan.']);
        }

        // Validasi kelengkapan & rentang jawaban
        foreach ($questions as $q) {
            $given = $validated['answers'][$q->id] ?? null;

            if ($given === null) {
                return redirect()->back()->withErrors(['answers' => 'Semua pertanyaan harus dijawab.']);
            }

            $given = (int) $given;

            if ($given < 0 || $given >= count($q->options)) {
                return redirect()->back()->withErrors(['answers' => 'Terdapat jawaban yang tidak valid.']);
            }
        }

        // SCORING SERVER-SIDE
        $correct = 0;

        foreach ($questions as $q) {
            if ((int) $validated['answers'][$q->id] === $q->correct_index) {
                $correct++;
            }
        }

        $score = (int) round($correct / $questions->count() * 100);
        $passed = $score >= $quiz->passing_score;

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $request->user()->id,
            'tenant_id' => $request->user()->tenant_id, // server-side, bukan dari client
            'score' => $score,
            'passed' => $passed,
            'answers' => $validated['answers'],
        ]);

        // Integrasi ke assignment: simpan skor terbaik; lulus = modul selesai
        $assignment->score = max((int) ($assignment->score ?? 0), $score);

        if ($passed && $assignment->status !== 'completed') {
            $assignment->status = 'completed';
            $assignment->completed_at = now();
        }

        $assignment->save();

        Audit::log('quiz.submitted', $attempt, ['score' => $score, 'passed' => $passed]);

        return redirect()->route('user.quiz.result', $attempt);
    }

    public function result(Request $request, QuizAttempt $attempt)
    {
        if ($attempt->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak berhak melihat hasil ini.');
        }

        $attempt->load('quiz:id,title,passing_score,training_module_id');

        // Cari assignment milik user ini untuk modul yang sama (untuk tombol "Ulangi Quiz")
        $assignmentId = $request->user()->moduleAssignments()
            ->where('training_module_id', $attempt->quiz->training_module_id)
            ->value('id');

        return Inertia::render('User/MyTraining/Result', [
            'attempt' => $attempt,
            'assignment_id' => $assignmentId,
        ]);
    }

    private function ensureOwner(Request $request, ModuleAssignment $assignment): void
    {
        if ($assignment->user_id !== $request->user()->id) {
            abort(403, 'Anda tidak berhak mengakses assignment ini.');
        }
    }
}