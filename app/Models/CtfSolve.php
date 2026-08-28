<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CtfSolve extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'tenant_id', 'challenge_id', 'points', 'solved_at'];

    protected $casts = ['solved_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

        public function challenge(): BelongsTo
    {
        return $this->belongsTo(CtfChallenge::class, 'challenge_id');
    }
}