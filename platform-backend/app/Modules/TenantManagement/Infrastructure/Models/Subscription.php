<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Platform-level: the tenant's active commercial agreement. */
class Subscription extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['current_period_end' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
