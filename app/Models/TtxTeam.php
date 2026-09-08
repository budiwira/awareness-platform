<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $exercise_id
 * @property string $name
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read TtxExercise $exercise
 * @property-read Collection<int, TtxTeamMember> $members
 */
class TtxTeam extends Model
{
    protected $fillable = ['tenant_id', 'exercise_id', 'name', 'description'];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(TtxExercise::class, 'exercise_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TtxTeamMember::class, 'team_id');
    }
}
