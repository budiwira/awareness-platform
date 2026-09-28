<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TtxSessionTeam extends Model
{
    protected $fillable = ['name', 'description', 'responsibilities'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TtxSession::class, 'session_id');
    }

    /** @return HasMany<TtxSessionParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(TtxSessionParticipant::class, 'team_id');
    }

    /** @return HasMany<TtxSessionResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(TtxSessionResponse::class, 'session_team_id');
    }

    /** @return HasMany<TtxSessionResponsibilityAssignment, $this> */
    public function responsibilityAssignments(): HasMany
    {
        return $this->hasMany(TtxSessionResponsibilityAssignment::class, 'session_team_id');
    }
}
