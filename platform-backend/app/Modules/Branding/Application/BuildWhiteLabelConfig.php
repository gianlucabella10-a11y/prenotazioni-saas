<?php

declare(strict_types=1);

namespace App\Modules\Branding\Application;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\Catalog\Infrastructure\Models\Location;

/**
 * Builds the runtime WhiteLabelConfig consumed by the Flutter client at
 * startup (docs/27 §2). Served for EVERY tenant status: suspended and
 * terminated tenants get the minimal payload that drives the courtesy
 * screen instead of a hard error (docs/27 §7).
 */
final readonly class BuildWhiteLabelConfig
{
    public function __construct(private CurrentTenant $currentTenant) {}

    /** @return array{etag: string, payload: array<string, mixed>} */
    public function execute(): array
    {
        $tenant = $this->currentTenant->get();

        $brand = BrandProfile::query()->firstOrFail();

        if (! $tenant->status->acceptsCustomerTraffic()) {
            return [
                'etag' => "\"cfg-{$tenant->uuid}-{$brand->config_version}-{$tenant->status->value}\"",
                'payload' => [
                    'tenant_status' => $tenant->status->value,
                    'app_name' => $brand->app_name,
                    'message_key' => $tenant->status->isTerminal() ? 'service_terminated' : 'service_suspended',
                ],
            ];
        }

        $locations = Location::query()
            ->where('status', 'active')
            ->get()
            ->map(static fn (Location $l): array => [
                'uuid' => $l->uuid,
                'name' => $l->name,
                'address' => $l->address,
                'phone' => $l->phone,
                'timezone' => $l->timezone,
                'booking_window_days' => $l->booking_window_days,
                'cancellation_cutoff_minutes' => $l->cancellation_cutoff_minutes,
            ])
            ->all();

        $payload = [
            'tenant_status' => $tenant->status->value,
            'config_version' => $brand->config_version,
            'app_name' => $brand->app_name,
            'tagline' => $brand->tagline,
            'theme' => $brand->theme,
            'locale_default' => $tenant->locale,
            'features' => $tenant->features,
            'booking' => [
                'confirmation_mode' => $tenant->requiresBookingApproval() ? 'request_approve' : 'auto_confirm',
            ],
            // White-label legal/support links (Fase 3+5: GDPR + store
            // compliance). Configurable per tenant from the brand profile.
            'legal' => [
                'privacy_policy_url' => $brand->privacy_policy_url,
                'terms_url' => $brand->terms_url,
                'support_url' => $brand->support_url,
            ],
            'locations' => $locations,
        ];

        return [
            'etag' => "\"cfg-{$tenant->uuid}-{$brand->config_version}\"",
            'payload' => $payload,
        ];
    }
}
