<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CtfChallenge extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'category', 'difficulty', 'points', 'flag', 'hint', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    // Flag tidak boleh ikut ter-serialize ke response Inertia/API
    protected $hidden = ['flag'];

        public function solves(): HasMany
    {
        return $this->hasMany(CtfSolve::class, 'challenge_id');
    }
}