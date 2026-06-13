<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use App\Modules\Scheduling\Domain\AppointmentStatus;
use App\Modules\Scheduling\Domain\Exceptions\InvalidStatusTransition;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Catalog\Infrastructure\Models\Location;
use App\Modules\Customers\Infrastructure\Models\Customer;

/**
 * Aggregate root: an appointment with one or more items (service × staff ×
 * interval). All status changes go through transitionTo, which validates
 * the state machine and records an AppointmentEvent (docs/30 §5).
 *
 * @property AppointmentStatus $status
 */
class Appointment extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AppointmentItem::class)->orderBy('position');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AppointmentEvent::class);
    }

    /**
     * Validated state transition: updates status timestamps, item blocking
     * flags and the functional event history, atomically with the caller's
     * transaction.
     */
    public function transitionTo(
        AppointmentStatus $target,
        string $actorType,
        ?int $actorId = null,
        ?string $reason = null,
    ): void {
        $from = $this->status;

        if (! $from->canTransitionTo($target)) {
            throw InvalidStatusTransition::between($from, $target);
        }

        $this->status = $target;

        match (true) {
            $target === AppointmentStatus::Confirmed => $this->confirmed_at = now(),
            $target->isCancelled() => $this->cancelled_at = now(),
            $target === AppointmentStatus::Completed => $this->completed_at = now(),
            default => null,
        };

        if ($target->isCancelled() && $reason !== null) {
            $this->cancellation_reason = $reason;
        }

        $this->save();

        // Cancelled items stop blocking the agenda (NULL escapes the unique
        // index, freeing the slot for re-booking — docs/33 #10).
        if (! $target->blocksAgenda()) {
            $this->items()->update(['is_blocking' => null]);
        }

        $this->events()->create([
            'tenant_id' => $this->tenant_id,
            'from_status' => $from->value,
            'to_status' => $target->value,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    protected static function newFactory(): AppointmentFactory
    {
        return AppointmentFactory::new();
    }
}
