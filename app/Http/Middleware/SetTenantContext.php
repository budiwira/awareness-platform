<?php

namespace App\Http\Middleware;

use App\Support\Tenant\CurrentTenant;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $db = DB::connection();
        $tenant = app(CurrentTenant::class);

        try {
            $guard = Auth::guard('web');

            if (! $guard instanceof SessionGuard) {
                throw new LogicException('The web authentication guard must use the session driver.');
            }

            $sessionUserId = $request->session()->get($guard->getName());
            $bootstrapUserId = (is_int($sessionUserId) || (is_string($sessionUserId) && ctype_digit($sessionUserId)))
                ? (string) $sessionUserId
                : '';

            $db->statement("SELECT set_config('app.user_id', ?, false), set_config('app.role', '', false), set_config('app.tenant_id', '', false)", [
                $bootstrapUserId,
            ]);

            $user = $request->user();
            $db->statement("SELECT set_config('app.user_id', ?, false), set_config('app.role', ?, false), set_config('app.tenant_id', ?, false)", [
                $user ? (string) $user->id : '',
                $user?->role->value ?? '',
                $user->tenant_id ?? '',
            ]);
            $tenant->set($user?->tenant_id);

            return $next($request);
        } finally {
            $tenant->set(null);
            $db->statement("SELECT set_config('app.user_id', '', false), set_config('app.role', '', false), set_config('app.tenant_id', '', false)");
        }
    }
}
