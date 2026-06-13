<?php

declare(strict_types=1);

namespace App\Modules\Customers\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Internal or clinical note on a customer. Body is encrypted at rest;
 * clinical notes additionally require the tenant's health-data module and
 * per-role authorization (docs/26 §5).
 */
class CustomerNote extends Model
{
    use BelongsToTenant;

    public const VISIBILITY_INTERNAL = 'internal';
    public const VISIBILITY_CLINICAL = 'clinical';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isClinical(): bool
    {
        return $this->visibility === self::VISIBILITY_CLINICAL;
    }
}
