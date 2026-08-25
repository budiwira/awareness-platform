<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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