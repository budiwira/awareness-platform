<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/**
 * @property int $id
 * @property string $tenant_id
 * @property string $title
 * @property string $scenario
 * @property string $objectives
 * @property string $scope
 * @property int|null $playbook_id
 * @property int|null $runbook_id
 * @property string $phase
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property string|null $aar_notes
 * @property array|null $corrective_actions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TtxPlaybook|null $playbook
 * @property-read \App\Models\TtxRunbook|null $runbook
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TtxInject> $injects
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TtxTeam> $teams
 */
class TtxExercise extends Model
{
    protected $fillable = ['tenant_id', 'title', 'scenario', 'objectives', 'scope', 'playbook_id', 'runbook_id', 'phase', 'scheduled_at', 'aar_notes', 'corrective_actions'];
    protected $casts = ['scheduled_at' => 'datetime', 'corrective_actions' => 'array'];
    public function playbook(): BelongsTo { return $this->belongsTo(TtxPlaybook::class, 'playbook_id'); }
    public function runbook(): BelongsTo { return $this->belongsTo(TtxRunbook::class, 'runbook_id'); }
    public function injects(): HasMany { return $this->hasMany(TtxInject::class, 'exercise_id')->orderBy('order'); }
    public function teams(): HasMany { return $this->hasMany(TtxTeam::class, 'exercise_id'); }
}