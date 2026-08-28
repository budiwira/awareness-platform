<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class TtxRunbook extends Model
{
    protected $fillable = ['tenant_id', 'title', 'description', 'steps', 'is_active'];
    protected $casts = ['is_active' => 'boolean', 'steps' => 'array'];
    public function exercises(): HasMany { return $this->hasMany(TtxExercise::class, 'runbook_id'); }
}