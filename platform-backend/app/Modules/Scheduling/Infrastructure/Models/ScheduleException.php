<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Catalog\Infrastructure\Models\Location;
use App\Modules\Staff\Infrastructure\Models\StaffMember;

/**
 * Punctual schedule deviation: closures (holidays, sickness) or extra
 * openings, scoped to a location or a single staff member.
 */
class ScheduleException extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const SCOPE_LOCATION = 'location';
    public const SCOPE_STAFF = 'staff';

    public const KIND_CLOSED = 'closed';
    public const KIND_OPEN_EXTRA = 'open_extra';

    protected $guarded = ['id'];

    // date_start/date_end are plain 'Y-m-d' strings on purpose: Eloquent
    // date casts serialize with a time component, breaking string-range
    // comparisons on SQLite and forcing index-hostile DATE() wrapping on
    // MySQL. Local calendar dates are not instants (docs/30 §2).

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }
}
