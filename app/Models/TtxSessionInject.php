<?php

namespace App\Models;

use App\Enums\TtxSessionInjectStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $session_id
 * @property int $inject_id
 * @property int $order
 * @property TtxSessionInjectStatus $status
 * @property array|null $inject_snapshot
 * @property Carbon|null $released_at
 * @property Carbon|null $locked_at
 * @property int|null $released_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TtxSession $session
 * @property-read TtxInject $inject
 * @property-read User|null $releasedBy
 */
class TtxSessionInject extends Model
{
    protected $fillable = [
        'session_id',
        'inject_id',
        'order',
        'status',
        'inject_snapshot',
        'released_at',
        'locked_at',
    ];

    protected $casts = [
        'status' => TtxSessionInjectStatus::class,
        'inject_snapshot' => 'array',
        'released_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TtxSession::class, 'session_id');
    }

    public function inject(): BelongsTo
    {
        return $this->belongsTo(TtxInject::class, 'inject_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
