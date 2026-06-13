<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Platform-level: maps dashboard (sub)domains to tenants. */
class TenantDomain extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_primary' => 'bool'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
