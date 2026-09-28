<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $exercise_id
 * @property int $order
 * @property string $title
 * @property string $description
 * @property array<int, string>|null $capability_codes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read TtxExercise $exercise
 */
class TtxInject extends Model
{
    protected $fillable = ['tenant_id', 'exercise_id', 'order', 'title', 'description'];

    protected $casts = ['capability_codes' => 'array'];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(TtxExercise::class, 'exercise_id');
    }
}
