<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $training_module_id
 * @property string $title
 * @property int $passing_score
 * @property int|null $duration_minutes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TrainingModule $module
 * @property-read Collection<int, QuizQuestion> $questions
 * @property-read Collection<int, QuizAttempt> $attempts
 */
class Quiz extends Model
{
    use HasFactory;

    protected $fillable = ['training_module_id', 'title', 'passing_score', 'duration_minutes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
