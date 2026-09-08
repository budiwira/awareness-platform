<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $quiz_id
 * @property int $user_id
 * @property string $tenant_id
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $deadline_at
 * @property Carbon|null $submitted_at
 * @property int|null $score
 * @property bool|null $passed
 * @property array|null $answers
 * @property array|null $question_order
 * @property array|null $option_orders
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Quiz $quiz
 * @property-read User $user
 */
class QuizAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'user_id',
        'tenant_id',
        'status',
        'started_at',
        'deadline_at',
        'submitted_at',
        'score',
        'passed',
        'answers',
        'question_order',
        'option_orders',
    ];

    protected $casts = [
        'answers' => 'array',
        'question_order' => 'array',
        'option_orders' => 'array',
        'passed' => 'boolean',
        'started_at' => 'datetime',
        'deadline_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
