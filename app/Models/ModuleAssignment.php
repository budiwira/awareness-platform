<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $tenant_id
 * @property int $training_module_id
 * @property int|null $assigned_by
 * @property int|null $pretest_quiz_id
 * @property int|null $posttest_quiz_id
 * @property string $status
 * @property Carbon|null $completed_at
 * @property Carbon|null $content_started_at
 * @property Carbon|null $content_completed_at
 * @property Carbon|null $cancelled_at
 * @property int|null $score
 * @property int|null $pretest_score
 * @property Carbon|null $pretest_completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read TrainingModule $module
 * @property-read User|null $assignedBy
 * @property-read Quiz|null $pretestQuiz
 * @property-read Quiz|null $posttestQuiz
 */
class ModuleAssignment extends Model
{
    use HasFactory;

    protected $hidden = [
        'module_snapshot',
    ];

    protected static function booted(): void
    {
        static::creating(function (ModuleAssignment $assignment): void {
            $assignment->assigned_at ??= now();
            if ($assignment->module_snapshot === null) {
                $module = TrainingModule::findOrFail($assignment->training_module_id);
                $assignment->module_snapshot = $module->runtimeSnapshot();
                $assignment->pretest_quiz_id ??= $module->pretest_quiz_id;
                $assignment->posttest_quiz_id ??= $module->posttest_quiz_id;

                if (! $assignment->pretest_quiz_id && ! $assignment->posttest_quiz_id) {
                    $legacyQuiz = $module->quiz;
                    if ($legacyQuiz?->purpose === 'posttest') {
                        $assignment->posttest_quiz_id = $legacyQuiz->id;
                    }
                }
            }
        });
    }

    protected $fillable = [
        'user_id',
        'tenant_id',
        'training_module_id',
        'assigned_by',
        'assigned_at',
        'deadline_at',
        'started_at',
        'module_snapshot',
        'pretest_quiz_id',
        'posttest_quiz_id',
        'status',
        'completed_at',
        'score',
        'pretest_score',
        'pretest_completed_at',
        'content_started_at',
        'content_completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'pretest_completed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'deadline_at' => 'datetime',
        'started_at' => 'datetime',
        'content_started_at' => 'datetime',
        'content_completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'module_snapshot' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** @return BelongsTo<Quiz, $this> */
    public function pretestQuiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'pretest_quiz_id');
    }

    /** @return BelongsTo<Quiz, $this> */
    public function posttestQuiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'posttest_quiz_id');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function isOverdue(): bool
    {
        return $this->deadline_at !== null
            && $this->deadline_at->isPast()
            && ! in_array($this->status, ['completed', 'cancelled'], true);
    }
}
