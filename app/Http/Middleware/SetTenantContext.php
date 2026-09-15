<?php

namespace App\Http\Middleware;

use App\Support\Tenant\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $db = DB::connection();
        $tenant = app(CurrentTenant::class);

        try {
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
