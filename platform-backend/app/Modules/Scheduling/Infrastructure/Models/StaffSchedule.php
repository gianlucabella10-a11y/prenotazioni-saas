<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Catalog\Infrastructure\Models\Location;
use App\Modules\Staff\Infrastructure\Models\StaffMember;

/** Weekly working rule for a staff member at a location (local times). */
class StaffSchedule extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    // valid_from/valid_to are plain 'Y-m-d' strings (see ScheduleException).

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
