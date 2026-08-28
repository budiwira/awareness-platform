<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TtxInject extends Model
{
    protected $fillable = ['tenant_id', 'exercise_id', 'order', 'title', 'description'];
    public function exercise(): BelongsTo { return $this->belongsTo(TtxExercise::class); }
}