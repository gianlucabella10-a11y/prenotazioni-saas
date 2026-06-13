<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Staff\Infrastructure\Models\StaffMember;

/**
 * A bookable service. Duration and price always live on variants: the
 * simple case is a single default variant (docs/24 §4), which unifies the
 * booking flow and the availability engine.
 */
class Service extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'bool'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ServiceVariant::class);
    }

    public function staffMembers(): BelongsToMany
    {
        return $this->belongsToMany(StaffMember::class, 'staff_services');
    }

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }
}
