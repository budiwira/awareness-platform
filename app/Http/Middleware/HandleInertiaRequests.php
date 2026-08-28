<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? array_merge(
                    $request->user()->only(['id', 'name', 'email']),
                    [
                        'role' => $request->user()->role->value,
                        'role_label' => ucwords(str_replace('_', ' ', $request->user()->role->value)),
                        'tenant_id' => $request->user()->tenant_id,
                        'tenant_name' => $request->user()->tenant?->name,
                    ]
                ) : null,
            ],
            'unread' => $request->user() ? $request->user()->unreadNotifications()->count() : 0,
        ]);
    }
}