<?php

namespace App\Models;

use App\Enums\TtxSessionRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $session_id
 * @property int $user_id
 * @property TtxSessionRole $session_role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TtxSession $session
 * @property-read User $user
 * @property-read TtxSessionTeam|null $team
 */
class TtxSessionParticipant extends Model
{
    protected $fillable = ['tenant_id', 'session_id', 'user_id', 'team_id', 'session_role'];

    protected $casts = [
        'session_role' => TtxSessionRole::class,
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TtxSession::class, 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(TtxSessionTeam::class, 'team_id');
    }
}
