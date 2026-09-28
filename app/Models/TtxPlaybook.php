<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $title
 * @property string|null $description
 * @property string|null $content
 * @property array<int, array<string, mixed>>|null $structured_phases
 * @property bool $is_active
 */
class TtxPlaybook extends Model
{
    protected $fillable = ['tenant_id', 'title', 'description', 'content', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'structured_phases' => 'array',
    ];

    public function exercises(): HasMany
    {
        return $this->hasMany(TtxExercise::class, 'playbook_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TtxSession::class, 'playbook_id');
    }
}
