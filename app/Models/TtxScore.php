<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $exercise_id
 * @property int $user_id
 * @property int|null $score
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read TtxExercise $exercise
 */
class TtxScore extends Model
{
    protected $fillable = ['tenant_id', 'exercise_id', 'user_id', 'score'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(TtxExercise::class, 'exercise_id');
    }
}
