<?php

namespace App\Http\Middleware;

use App\Support\Tenant\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = Auth::id();

        DB::statement("SELECT set_config('app.user_id', ?, false)", [
            $userId !== null ? (string) $userId : '',
        ]);

        $user = $request->user();

        if ($user !== null) {
            DB::statement("SELECT set_config('app.role', ?, false)", [$user->role->value]);
            DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$user->tenant_id ?? '']);

            if ($user->tenant_id !== null) {
                app(CurrentTenant::class)->set((string) $user->tenant_id);
            }
        } else {
            DB::statement("SELECT set_config('app.role', '', false)");
            DB::statement("SELECT set_config('app.tenant_id', '', false)");
        }

        return $next($request);
    }
}
