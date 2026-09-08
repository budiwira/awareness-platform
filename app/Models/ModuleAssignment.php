<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $tenant_id
 * @property int $training_module_id
 * @property string $status
 * @property Carbon|null $completed_at
 * @property int|null $score
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read TrainingModule $module
 */
class ModuleAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'training_module_id',
        'status',
        'completed_at',
        'score',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }
}
