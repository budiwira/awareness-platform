<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string $difficulty
 * @property int $duration_minutes
 * @property bool $is_active
 * @property string|null $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CaseScene> $scenes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CaseParticipation> $participations
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