<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/**
 * @property int $id
 * @property string $tenant_id
 * @property int $exercise_id
 * @property int $user_id
 * @property int|null $score
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\TtxExercise $exercise
 */
class TtxScore extends Model
{
    protected $fillable = ['tenant_id', 'exercise_id', 'user_id', 'score'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function exercise(): BelongsTo { return $this->belongsTo(TtxExercise::class, 'exercise_id'); }
}