<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use Database\Factories\ServiceVariantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The bookable unit: carries duration, buffer and price for one variant of
 * a service (e.g. "Taglio — capelli lunghi").
 */
class ServiceVariant extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<ServiceVariantFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_default' => 'bool',
            'is_active' => 'bool',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** Minutes the variant occupies on the agenda, buffer included. */
    public function blockingMinutes(): int
    {
        return $this->duration_minutes + $this->buffer_after_minutes;
    }

    protected static function newFactory(): ServiceVariantFactory
    {
        return ServiceVariantFactory::new();
    }
}
