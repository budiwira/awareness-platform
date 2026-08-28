<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class TtxTeam extends Model
{
    protected $fillable = ['tenant_id', 'exercise_id', 'name', 'description'];
    public function exercise(): BelongsTo { return $this->belongsTo(TtxExercise::class); }
    public function members(): HasMany { return $this->hasMany(TtxTeamMember::class); }
}