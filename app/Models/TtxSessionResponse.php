<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $session_id
 * @property int $session_inject_id
 * @property int|null $session_team_id
 * @property string $decision
 * @property string|null $rationale
 * @property string|null $owner
 * @property string|null $immediate_actions
 * @property string|null $coordination_handoff
 * @property string|null $escalation
 * @property string|null $unknowns
 * @property string|null $notes
 * @property int $revision
 * @property int $submitted_by
 * @property Carbon $submitted_at
 * @property int|null $last_edited_by
 * @property Carbon|null $locked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TtxSession $session
 * @property-read TtxSessionInject $sessionInject
 * @property-read TtxSessionTeam|null $sessionTeam
 * @property-read User $submittedBy
 * @property-read User|null $lastEditedBy
 */
class TtxSessionResponse extends Model
{
    protected $fillable = [
        'decision',
        'rationale',
        'owner',
        'immediate_actions',
        'coordination_handoff',
        'escalation',
        'unknowns',
        'notes',
    ];

    protected $casts = [
        'revision' => 'integer',
        'submitted_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TtxSession::class, 'session_id');
    }

    public function sessionInject(): BelongsTo
    {
        return $this->belongsTo(TtxSessionInject::class, 'session_inject_id');
    }

    public function sessionTeam(): BelongsTo
    {
        return $this->belongsTo(TtxSessionTeam::class, 'session_team_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function lastEditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }
}
