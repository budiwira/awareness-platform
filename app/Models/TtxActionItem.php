<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $session_id
 * @property string $title
 * @property string $owner
 * @property string $priority
 * @property Carbon|null $due_date
 * @property string $status
 * @property int $created_by
 * @property int $updated_by
 */
class TtxActionItem extends Model
{
    protected $fillable = ['title', 'owner', 'priority', 'due_date', 'status'];

    protected $casts = ['due_date' => 'date'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TtxSession::class, 'session_id');
    }
}
