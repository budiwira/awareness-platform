<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $package_id
 * @property string $status
 * @property Carbon $started_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Package $package
 * @property-read Tenant $tenant
 */
class Subscription extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'package_id', 'status', 'started_at', 'ends_at'];

    protected $casts = ['started_at' => 'datetime', 'ends_at' => 'datetime'];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
