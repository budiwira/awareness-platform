<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseScene extends Model
{
    use HasFactory;

    protected $fillable = ['case_study_id', 'order', 'situation', 'options'];

    protected $casts = ['options' => 'array'];

    public function caseStudy(): BelongsTo
    {
        return $this->belongsTo(CaseStudy::class);
    }
}