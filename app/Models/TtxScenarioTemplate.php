<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property string|null $scenario
 * @property string|null $objectives
 * @property string|null $scope
 * @property list<string>|null $capability_codes
 * @property string $status
 */
class TtxScenarioTemplate extends Model
{
    protected $fillable = ['title', 'scenario', 'objectives', 'scope', 'capability_codes', 'status'];

    protected $casts = ['capability_codes' => 'array'];

    /** @return HasMany<TtxScenarioInjectTemplate, $this> */
    public function injects(): HasMany
    {
        return $this->hasMany(TtxScenarioInjectTemplate::class, 'scenario_template_id')->orderBy('order');
    }

    /** @return HasMany<TtxExercise, $this> */
    public function exercises(): HasMany
    {
        return $this->hasMany(TtxExercise::class, 'source_scenario_template_id');
    }
}
