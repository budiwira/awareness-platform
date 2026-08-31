<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'price_monthly', 'max_users', 'features', 'includes_all_modules', 'is_active'];

    protected $casts = ['features' => 'array', 'includes_all_modules' => 'boolean', 'is_active' => 'boolean'];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function modules(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(TrainingModule::class, 'plan_module')->withTimestamps();
    }
}