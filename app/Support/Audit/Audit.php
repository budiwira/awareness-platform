<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function log(string $action, ?Model $subject = null, array $properties = []): AuditLog
    {
        $request = request();
        $user = $request->user();

        return AuditLog::create([
            'tenant_id' => $user?->tenant_id,
            'actor_user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject ? (string) $subject->getKey() : null,
            'properties' => $properties !== [] ? $properties : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? (string) substr((string) $request->userAgent(), 0, 500) : null,
            'created_at' => now(),
        ]);
    }
}