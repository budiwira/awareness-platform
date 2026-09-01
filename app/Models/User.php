<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $avatar_path
 * @property string|null $tenant_id
 * @property \App\Enums\UserRole $role
 * @property bool $is_active
 * @property int $login_streak
 * @property \Illuminate\Support\Carbon|null $last_login_date
 * @property bool $show_on_leaderboard
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Tenant|null $tenant
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ModuleAssignment> $moduleAssignments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ModuleAssignment> $assignments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CaseParticipation> $caseParticipations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CtfSolve> $ctfSolves
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TtxScore> $ttxScores
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QuizAttempt> $quizAttempts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PhishingTarget> $phishingTargets
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PhishingCampaign> $phishingCampaignsCreated
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserBadge> $userBadges
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Badge> $badges
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'avatar_path',
        'password',
        'tenant_id',
        'role',
        'is_active',
        'login_streak',
        'last_login_date',
        'show_on_leaderboard',
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
            'login_streak' => 'integer',
            'last_login_date' => 'date',
            'show_on_leaderboard' => 'boolean',
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

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ModuleAssignment, $this>
     */
    public function assignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ModuleAssignment::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\QuizAttempt, $this>
     */
    public function quizAttempts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\PhishingTarget, $this>
     */
    public function phishingTargets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PhishingTarget::class);
    }

    public function phishingCampaignsCreated()
    {
        return $this->hasMany(PhishingCampaign::class, 'created_by');
    }

    public function userBadges()
    {
        return $this->hasMany(UserBadge::class);
    }

    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'user_badges')
            ->withTimestamps()
            ->withPivot('earned_at');
    }
}