<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFeatureAccess extends Model
{
    protected $table = 'user_feature_access';

    protected $fillable = [
        'user_id',
        'feature_key',
        'tenant_id',
        'is_allowed',
        'granted_by',
        'granted_at',
    ];

    protected $casts = [
        'is_allowed' => 'boolean',
        'granted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}