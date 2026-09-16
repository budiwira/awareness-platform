<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => (string) $this->string('email'),
            'password' => (string) $this->string('password'),
            'is_active' => true,
        ];

        try {
            $authenticated = DB::transaction(function () use ($credentials): bool {
                DB::statement("SELECT set_config('app.auth_email', ?, true)", [$credentials['email']]);

                try {
                    return Auth::attemptWhen(
                        $credentials,
                        function (User $user): bool {
                            DB::statement("SELECT set_config('app.user_id', ?, false), set_config('app.role', ?, false), set_config('app.tenant_id', ?, false)", [
                                (string) $user->id,
                                $user->role->value,
                                $user->tenant_id ?? '',
                            ]);

                            return true;
                        },
                        $this->boolean('remember'),
                    );
                } finally {
                    DB::statement("SELECT set_config('app.auth_email', '', true)");
                }
            });
        } catch (Throwable $exception) {
            $this->clearAuthenticatedDatabaseContext();

            throw $exception;
        }

        if (! $authenticated) {
            $this->clearAuthenticatedDatabaseContext();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        Audit::log('auth.login');

        RateLimiter::clear($this->throttleKey());
    }

    private function clearAuthenticatedDatabaseContext(): void
    {
        DB::statement("SELECT set_config('app.user_id', '', false), set_config('app.role', '', false), set_config('app.tenant_id', '', false)");
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
