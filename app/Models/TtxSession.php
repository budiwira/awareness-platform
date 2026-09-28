<?php

namespace App\Models;

use App\Enums\TtxSessionStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $exercise_id
 * @property int|null $playbook_id
 * @property string $title
 * @property TtxSessionStatus $status
 * @property array|null $exercise_snapshot
 * @property array|null $playbook_snapshot
 * @property int $response_contract_version
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $debrief_started_at
 * @property Carbon|null $completed_at
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TtxExercise $exercise
 * @property-read TtxPlaybook|null $playbook
 * @property-read Collection<int, TtxSessionParticipant> $participants
 * @property-read Collection<int, TtxSessionTeam> $teams
 * @property-read User $facilitator
 * @property-read Collection<int, TtxSessionInject> $injects
 * @property-read Collection<int, TtxSessionResponse> $responses
 * @property-read Collection<int, TtxSessionEvaluation> $evaluations
 * @property-read Collection<int, TtxActionItem> $actionItems
 * @property-read TtxAfterActionSummary|null $afterActionSummary
 */
class TtxSession extends Model
{
    protected $fillable = [
        'tenant_id',
        'exercise_id',
        'playbook_id',
        'title',
        'created_by',
        'status',
        'exercise_snapshot',
        'playbook_snapshot',
        'response_contract_version',
        'scheduled_at',
        'started_at',
        'debrief_started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => TtxSessionStatus::class,
        'exercise_snapshot' => 'array',
        'playbook_snapshot' => 'array',
        'response_contract_version' => 'integer',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'debrief_started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(TtxExercise::class, 'exercise_id');
    }

    public function playbook(): BelongsTo
    {
        return $this->belongsTo(TtxPlaybook::class, 'playbook_id');
    }

    /**
     * @return HasMany<TtxSessionParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(TtxSessionParticipant::class, 'session_id');
    }

    /** @return HasMany<TtxSessionTeam, $this> */
    public function teams(): HasMany
    {
        return $this->hasMany(TtxSessionTeam::class, 'session_id')->orderBy('name');
    }

    /** @return HasMany<TtxSessionResponsibilityAssignment, $this> */
    public function responsibilityAssignments(): HasMany
    {
        return $this->hasMany(TtxSessionResponsibilityAssignment::class, 'session_id');
    }

    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<TtxSessionInject, $this>
     */
    public function injects(): HasMany
    {
        return $this->hasMany(TtxSessionInject::class, 'session_id')->orderBy('order');
    }

    /** @return HasMany<TtxSessionResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(TtxSessionResponse::class, 'session_id');
    }

    /** @return HasMany<TtxSessionEvaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(TtxSessionEvaluation::class, 'session_id');
    }

    /** @return HasMany<TtxActionItem, $this> */
    public function actionItems(): HasMany
    {
        return $this->hasMany(TtxActionItem::class, 'session_id')->orderBy('id');
    }

    /** @return HasOne<TtxAfterActionSummary, $this> */
    public function afterActionSummary(): HasOne
    {
        return $this->hasOne(TtxAfterActionSummary::class, 'session_id');
    }
}
