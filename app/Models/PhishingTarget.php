<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $campaign_id
 * @property int $user_id
 * @property string $token
 * @property string $status
 * @property Carbon|null $clicked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PhishingCampaign $campaign
 * @property-read User $user
 */
class PhishingTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'user_id',
        'token',
        'status',
        'clicked_at',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PhishingCampaign::class, 'campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
