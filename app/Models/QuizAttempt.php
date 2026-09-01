<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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