<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Scheduling\Infrastructure\Models\LocationSchedule;

/**
 * A bookable site of the tenant. Carries the IANA timezone in which all of
 * its recurring schedules are interpreted, plus the booking policy knobs
 * (window, cutoff, notice, granularity — docs/30 §9).
 */
class Location extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<LocationFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(LocationSchedule::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'location_services');
    }

    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }
}
