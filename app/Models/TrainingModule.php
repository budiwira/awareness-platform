<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string $content
 * @property int $duration_minutes
 * @property bool $is_active
 * @property string|null $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ModuleAssignment> $assignments
 * @property-read Quiz|null $quiz
 * @property-read Collection<int, Package> $packages
 */
class TrainingModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'content',
        'duration_minutes',
        'is_active',
        'status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(ModuleAssignment::class);
    }

    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class, 'training_module_id');
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'plan_module');
    }
}
