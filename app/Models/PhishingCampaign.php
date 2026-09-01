<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $title
 * @property string $sender_name
 * @property string $subject
 * @property string $body_template
 * @property string $status
 * @property int $created_by
 * @property array|null $email_snapshot
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Tenant $tenant
 * @property-read \App\Models\User $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PhishingTarget> $targets
 */
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
