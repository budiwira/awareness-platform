<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class TtxPlaybook extends Model
{
    protected $fillable = ['tenant_id', 'title', 'description', 'content', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function exercises(): HasMany { return $this->hasMany(TtxExercise::class, 'playbook_id'); }
}