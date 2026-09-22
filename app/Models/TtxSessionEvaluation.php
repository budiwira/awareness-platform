<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $session_id
 * @property string $dimension
 * @property string $rating
 * @property string|null $evidence
 * @property int $updated_by
 */
class TtxSessionEvaluation extends Model
{
    protected $fillable = ['rating', 'evidence'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TtxSession::class, 'session_id');
    }
}
