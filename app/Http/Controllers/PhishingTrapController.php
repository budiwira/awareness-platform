<?php

namespace App\Http\Controllers;

use App\Models\ModuleAssignment;
use App\Models\PhishingTarget;
use App\Models\TrainingModule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PhishingTrapController extends Controller
{
    public function show(Request $request, string $token)
    {
        $target = PhishingTarget::where('token', $token)
            ->where('status', 'sent')
            ->first();

        if (!$target) {
            abort(404);
        }

        // Mark as clicked
        $target->update([
            'status' => 'clicked',
            'clicked_at' => now(),
        ]);

        // Auto-remedial: assign training module if available
        $this->autoAssignRemedial($target);

        return Inertia::render('Public/PhishingTeaching', [
            'campaignTitle' => $target->campaign->title,
            'remedialAssigned' => session('remedial_assigned', false),
        ]);
    }

    private function autoAssignRemedial(PhishingTarget $target): void
    {
        // Cari modul remedial (tag phishing-remedial atau judul mengandung "phishing")
        $remedialModule = TrainingModule::where('status', 'published')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('title', 'like', '%Phishing%')
                  ->orWhere('title', 'like', '%Email%');
            })
            ->first();

        if (!$remedialModule) {
            return;
        }

        // Check jika user sudah punya assignment untuk modul ini
        $existingAssignment = ModuleAssignment::where('user_id', $target->user_id)
            ->where('training_module_id', $remedialModule->id)
            ->where('tenant_id', $target->campaign->tenant_id)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->exists();

        if ($existingAssignment) {
            return;
        }

        // Buat assignment baru
        ModuleAssignment::create([
            'user_id' => $target->user_id,
            'tenant_id' => $target->campaign->tenant_id,
            'training_module_id' => $remedialModule->id,
            'status' => 'assigned',
        ]);

        session()->flash('remedial_assigned', true);
    }
}
