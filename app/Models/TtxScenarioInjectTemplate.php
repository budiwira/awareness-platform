<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $scenario_template_id
 * @property int $order
 * @property string $title
 * @property string|null $description
 * @property list<string>|null $capability_codes
 * @property string $status
 */
class TtxScenarioInjectTemplate extends Model
{
    protected $fillable = ['scenario_template_id', 'order', 'title', 'description', 'capability_codes', 'status'];

    protected $casts = ['capability_codes' => 'array', 'order' => 'integer'];

    /** @return BelongsTo<TtxScenarioTemplate, $this> */
    public function scenarioTemplate(): BelongsTo
    {
        return $this->belongsTo(TtxScenarioTemplate::class, 'scenario_template_id');
    }
}
