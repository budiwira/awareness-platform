<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $tenant_id
 * @property int $case_study_id
 * @property string $status
 * @property int|null $score
 * @property array|null $decisions
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\CaseStudy $caseStudy
 */
class CaseParticipation extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'tenant_id', 'case_study_id', 'status', 'score', 'decisions', 'completed_at'];

    protected $casts = ['decisions' => 'array', 'completed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function caseStudy(): BelongsTo
    {
        return $this->belongsTo(CaseStudy::class);
    }
}