<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\UserBadge;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        // Hitung earned badges
        $earnedBadges = UserBadge::where('user_id', $user->id)->count();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'loginStreak' => $user->login_streak,
            'earnedBadges' => $earnedBadges,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->fill($validated);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        // Handle show_on_leaderboard if present
        if ($request->has('show_on_leaderboard')) {
            $request->user()->show_on_leaderboard = $request->boolean('show_on_leaderboard');
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Upload user avatar.
     */
    public function uploadAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        // Hapus file lama jika ada
        if ($user->avatar_path && \Storage::disk('public')->exists($user->avatar_path)) {
            \Storage::disk('public')->delete($user->avatar_path);
        }

        // Simpan file baru
        $file = $request->file('avatar');
        $filename = \Str::random(40).'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('avatars', $filename, 'public');

        // Update user
        $user->avatar_path = $path;
        $user->save();

        return Redirect::route('profile.edit')->with('status', 'Avatar berhasil diperbarui.');
    }
}
