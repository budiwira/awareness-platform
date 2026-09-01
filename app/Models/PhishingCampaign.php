<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhishingCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'title',
        'sender_name',
        'subject',
        'body_template',
        'status',
        'created_by',
        'email_snapshot',
    ];

    protected $casts = [
        'email_snapshot' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PhishingTarget::class, 'campaign_id');
    }
}
