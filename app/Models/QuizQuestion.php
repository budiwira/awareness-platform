<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $quiz_id
 * @property string $question
 * @property array $options
 * @property int $correct_index
 * @property string|null $explanation
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Quiz $quiz
 */
class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['quiz_id', 'question', 'options', 'correct_index', 'explanation'];

    protected $casts = ['options' => 'array'];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
