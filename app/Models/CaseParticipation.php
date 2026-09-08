<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $tenant_id
 * @property int $case_study_id
 * @property string $status
 * @property int|null $score
 * @property array|null $decisions
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read CaseStudy $caseStudy
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
