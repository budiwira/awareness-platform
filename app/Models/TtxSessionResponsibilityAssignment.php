<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TtxSessionResponsibilityAssignment extends Model
{
    protected $fillable = [];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TtxSession::class, 'session_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(TtxSessionTeam::class, 'session_team_id');
    }
}
