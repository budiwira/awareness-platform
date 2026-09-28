<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $session_id
 * @property string $overall_summary
 * @property string $strengths
 * @property string $improvement_areas
 * @property string $key_lessons
 * @property int $updated_by
 */
class TtxAfterActionSummary extends Model
{
    protected $fillable = ['overall_summary', 'strengths', 'improvement_areas', 'key_lessons'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TtxSession::class, 'session_id');
    }
}
