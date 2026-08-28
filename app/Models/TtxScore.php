<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TtxScore extends Model
{
    protected $fillable = ['tenant_id', 'exercise_id', 'user_id', 'score'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function exercise(): BelongsTo { return $this->belongsTo(TtxExercise::class); }
}