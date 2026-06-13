<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use App\Modules\Scheduling\Domain\TimeInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Staff\Infrastructure\Models\StaffMember;

/**
 * One line of an appointment: a service variant performed by a staff member
 * in a UTC interval (buffer included in ends_at). Carries catalog snapshots
 * so history survives price/name changes (docs/24).
 */
class AppointmentItem extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function interval(): TimeInterval
    {
        return new TimeInterval(
            $this->starts_at->toDateTimeImmutable(),
            $this->ends_at->toDateTimeImmutable(),
        );
    }
}
