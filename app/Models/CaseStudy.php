<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseStudy extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'difficulty', 'duration_minutes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scenes(): HasMany
    {
        return $this->hasMany(CaseScene::class)->orderBy('order');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(CaseParticipation::class);
    }
}