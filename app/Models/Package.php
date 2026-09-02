<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $price_monthly
 * @property int $max_users
 * @property array|null $features
 * @property bool $includes_all_modules
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subscription> $subscriptions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TrainingModule> $modules
 */
class Package extends Model
{
    use HasFactory;

    protected $table = 'packages';

    protected $fillable = ['name', 'slug', 'price_monthly', 'max_users', 'features', 'includes_all_modules', 'is_active', 'is_free'];

    protected $casts = ['features' => 'array', 'includes_all_modules' => 'boolean', 'is_active' => 'boolean', 'is_free' => 'boolean'];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function modules(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(TrainingModule::class, 'package_module');
    }
}