<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Presentation\Resources;

use App\Modules\Scheduling\Infrastructure\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appointment
 */
final class AppointmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'starts_at' => $this->starts_at->toIso8601ZuluString(),
            'ends_at' => $this->ends_at->toIso8601ZuluString(),
            'total_price_cents' => $this->total_price_cents,
            'currency' => $this->currency,
            'location_uuid' => $this->location->uuid,
            'location_name' => $this->location->name,
            'cancellation_reason' => $this->cancellation_reason,
            'items' => $this->items->map(static fn ($item): array => [
                'service_name' => $item->service_name_snapshot,
                'variant_name' => $item->variant_name_snapshot,
                'duration_minutes' => $item->duration_minutes_snapshot,
                'price_cents' => $item->price_cents_snapshot,
                'staff_name' => $item->staffMember->display_name,
                'staff_uuid' => $item->staffMember->uuid,
                'starts_at' => $item->starts_at->toIso8601ZuluString(),
            ])->all(),
        ];
    }
}
