<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isTenantAdmin(): bool
    {
        return $this->role === UserRole::TenantAdmin;
    }

    public function isUser(): bool
    {
        return $this->role === UserRole::User;
    }
        public function moduleAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ModuleAssignment::class);
    }
        public function caseParticipations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CaseParticipation::class);
    }
        public function ctfSolves(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CtfSolve::class);
    }
        public function ttxScores(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TtxScore::class);
    }
}