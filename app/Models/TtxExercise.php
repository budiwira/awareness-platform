<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class TtxExercise extends Model
{
    protected $fillable = ['tenant_id', 'title', 'scenario', 'objectives', 'scope', 'playbook_id', 'runbook_id', 'phase', 'scheduled_at', 'aar_notes', 'corrective_actions'];
    protected $casts = ['scheduled_at' => 'datetime', 'corrective_actions' => 'array'];
    public function playbook(): BelongsTo { return $this->belongsTo(TtxPlaybook::class, 'playbook_id'); }
    public function runbook(): BelongsTo { return $this->belongsTo(TtxRunbook::class, 'runbook_id'); }
    public function injects(): HasMany { return $this->hasMany(TtxInject::class, 'exercise_id')->orderBy('order'); }
    public function teams(): HasMany { return $this->hasMany(TtxTeam::class, 'exercise_id'); }
}