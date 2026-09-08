<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string $category
 * @property string $difficulty
 * @property int $points
 * @property string $flag
 * @property string|null $hint
 * @property bool $is_active
 * @property string|null $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CtfSolve> $solves
 */
class CtfChallenge extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'category', 'difficulty', 'points', 'flag', 'hint', 'is_active', 'status'];

    protected $casts = ['is_active' => 'boolean'];

    // Flag tidak boleh ikut ter-serialize ke response Inertia/API
    protected $hidden = ['flag'];

    public function solves(): HasMany
    {
        return $this->hasMany(CtfSolve::class, 'challenge_id');
    }
}
