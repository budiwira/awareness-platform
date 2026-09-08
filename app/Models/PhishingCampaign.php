<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read User $creator
 * @property-read Collection<int, PhishingTarget> $targets
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
