<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\UserBadge;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BadgeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Ambil semua badges
        $allBadges = Badge::where('is_active', true)
            ->orderBy('category')
            ->orderBy('criteria_value')
            ->get();

        // Ambil user_badges untuk user ini
        $earnedBadgeIds = UserBadge::where('user_id', $user->id)
            ->pluck('badge_id')
            ->toArray();

        // Format badges dengan status earned
        $badges = $allBadges->map(function ($badge) use ($earnedBadgeIds, $user) {
            $earned = in_array($badge->id, $earnedBadgeIds);
            $earnedAt = null;

            if ($earned) {
                $userBadge = UserBadge::where('user_id', $user->id)
                    ->where('badge_id', $badge->id)
                    ->first();
                $earnedAt = $userBadge?->earned_at;
            }

            return [
                'id' => $badge->id,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon' => $badge->icon,
                'category' => $badge->category,
                'earned' => $earned,
                'earned_at' => $earnedAt,
            ];
        });

        // Group by category
        $grouped = $badges->groupBy('category');

        return Inertia::render('User/Badges', [
            'badges' => $grouped,
            'totalBadges' => $allBadges->count(),
            'earnedCount' => count($earnedBadgeIds),
        ]);
    }
}
