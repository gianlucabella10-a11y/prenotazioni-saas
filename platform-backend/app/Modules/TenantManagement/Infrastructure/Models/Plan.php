<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform-level commercial plan. `features` holds the default flag set,
 * `quotas` the usage ceilings (docs/24, docs/28 §4).
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'quotas' => 'array',
            'is_active' => 'bool',
        ];
    }

    public function quota(string $key): ?int
    {
        $value = $this->quotas[$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }
}
