<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Catalog\Infrastructure\Models\Location;

/**
 * Weekly opening rule for a location: local wall-clock times, ISO weekday
 * (0 = Monday). Never stored in UTC (docs/30 §2).
 */
class LocationSchedule extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    // valid_from/valid_to are plain 'Y-m-d' strings (see ScheduleException).

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
