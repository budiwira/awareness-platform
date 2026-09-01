<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Update login streak
        $this->updateLoginStreak($request->user());

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Update user login streak.
     */
    private function updateLoginStreak($user): void
    {
        $today = now()->toDateString();
        $lastLogin = $user->last_login_date?->toDateString();

        if ($lastLogin === $today) {
            // Sudah login hari ini, tidak ada perubahan
            return;
        }

        $yesterday = now()->subDay()->toDateString();

        if ($lastLogin === $yesterday) {
            // Login kemarin, increment streak
            $user->login_streak += 1;
        } else {
            // Gap > 1 hari, reset streak ke 1
            $user->login_streak = 1;
        }

        $user->last_login_date = now();
        $user->save();

        // Check badge untuk streak
        app(\App\Services\BadgeAwardService::class)->checkStreak($user);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
