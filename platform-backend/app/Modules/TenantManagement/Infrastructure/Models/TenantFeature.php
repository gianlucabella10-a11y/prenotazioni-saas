<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Platform-level: per-tenant feature flag override (commercial exceptions). */
class TenantFeature extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'enabled' => 'bool',
            'params' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
