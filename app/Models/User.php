<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $avatar_path
 * @property string|null $tenant_id
 * @property UserRole $role
 * @property bool $is_active
 * @property int $login_streak
 * @property Carbon|null $last_login_date
 * @property bool $show_on_leaderboard
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $tenant
 * @property-read Collection<int, ModuleAssignment> $moduleAssignments
 * @property-read Collection<int, ModuleAssignment> $assignments
 * @property-read Collection<int, CaseParticipation> $caseParticipations
 * @property-read Collection<int, CtfSolve> $ctfSolves
 * @property-read Collection<int, TtxScore> $ttxScores
 * @property-read Collection<int, QuizAttempt> $quizAttempts
 * @property-read Collection<int, PhishingTarget> $phishingTargets
 * @property-read Collection<int, PhishingCampaign> $phishingCampaignsCreated
 * @property-read Collection<int, UserBadge> $userBadges
 * @property-read Collection<int, Badge> $badges
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

    public function moduleAssignments(): HasMany
    {
        return $this->hasMany(ModuleAssignment::class);
    }

    public function caseParticipations(): HasMany
    {
        return $this->hasMany(CaseParticipation::class);
    }

    public function ctfSolves(): HasMany
    {
        return $this->hasMany(CtfSolve::class);
    }

    public function ttxScores(): HasMany
    {
        return $this->hasMany(TtxScore::class);
    }

    /**
     * @return HasMany<ModuleAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ModuleAssignment::class);
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * @return HasMany<PhishingTarget, $this>
     */
    public function phishingTargets(): HasMany
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
