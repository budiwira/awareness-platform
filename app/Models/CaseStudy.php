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
 * @property string $difficulty
 * @property int $duration_minutes
 * @property bool $is_active
 * @property string|null $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CaseScene> $scenes
 * @property-read Collection<int, CaseParticipation> $participations
 */
class CaseStudy extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'difficulty', 'duration_minutes', 'is_active', 'status'];

    protected $casts = ['is_active' => 'boolean'];

    public function scenes(): HasMany
    {
        return $this->hasMany(CaseScene::class)->orderBy('order');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(CaseParticipation::class);
    }
}
