<?php

declare(strict_types=1);

namespace App\Foundation\Tenancy;

use App\Modules\TenantManagement\Domain\TenantStatus;
use DateTimeZone;

/**
 * Immutable snapshot of the tenant a request/job operates on.
 *
 * Built once per request (by the tenant resolution middleware or the JWT
 * guard) or per job (rehydrated from the serialized tenant id), then bound
 * into CurrentTenant. Domain and application code reads from here and never
 * re-resolves the tenant from user input (docs/28 §2, defence level 1).
 */
final readonly class TenantContext
{
    /**
     * @param array<string, bool> $features resolved feature flags (plan defaults + overrides)
     * @param array<string, mixed> $settings validated operational settings
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public TenantStatus $status,
        public string $timezone,
        public string $locale,
        public bool $healthDataEnabled,
        public array $features,
        public array $settings,
    ) {
    }

    public function hasFeature(string $code): bool
    {
        return ($this->features[$code] ?? false) === true;
    }

    public function timezoneObject(): DateTimeZone
    {
        return new DateTimeZone($this->timezone);
    }

    /**
     * Reminder offsets in hours before the appointment, most distant first.
     *
     * @return list<int>
     */
    public function reminderOffsetsHours(): array
    {
        /** @var list<int> $offsets */
        $offsets = $this->settings['reminder_offsets_hours'] ?? [24];

        rsort($offsets);

        return $offsets;
    }

    /** Whether bookings require manual approval (request/approve mode). */
    public function requiresBookingApproval(): bool
    {
        return ($this->settings['booking_confirmation_mode'] ?? 'auto_confirm') === 'request_approve';
    }
}
