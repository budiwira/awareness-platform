<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TtxTeamMember extends Model
{
    protected $fillable = ['tenant_id', 'team_id', 'user_id', 'role_in_team'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(TtxTeam::class, 'team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
