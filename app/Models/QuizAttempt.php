<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * @property int $id
 * @property int $quiz_id
 * @property int $module_assignment_id
 * @property int $user_id
 * @property string $tenant_id
 * @property string $assessment_purpose
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
 * @property-read ModuleAssignment $moduleAssignment
 * @property-read User $user
 */
class QuizAttempt extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (QuizAttempt $attempt): void {
            $quiz = Quiz::findOrFail($attempt->quiz_id);
            $attempt->assessment_purpose ??= $quiz->purpose;

            if ($attempt->module_assignment_id === null) {
                $matches = ModuleAssignment::query()
                    ->where('user_id', $attempt->user_id)
                    ->where('tenant_id', $attempt->tenant_id)
                    ->where('training_module_id', $quiz->training_module_id)
                    ->get();

                if ($matches->count() !== 1) {
                    throw new RuntimeException('Quiz attempt requires one unambiguous module assignment.');
                }

                $attempt->module_assignment_id = $matches->sole()->id;
            }
        });
    }

    protected $fillable = [
        'quiz_id',
        'module_assignment_id',
        'assessment_purpose',
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

    /** @return BelongsTo<ModuleAssignment, $this> */
    public function moduleAssignment(): BelongsTo
    {
        return $this->belongsTo(ModuleAssignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
