<?php

namespace App\Http\Controllers;

use App\Models\PhishingTarget;
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

        return Inertia::render('Public/PhishingTeaching', [
            'campaignTitle' => $target->campaign->title,
        ]);
    }
}
