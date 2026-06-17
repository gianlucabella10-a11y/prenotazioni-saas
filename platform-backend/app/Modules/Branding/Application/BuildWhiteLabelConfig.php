<?php

declare(strict_types=1);

namespace App\Modules\Branding\Application;

use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\AppFactory\Domain\TemplateRegistry;
use App\Modules\AppFactory\Infrastructure\Models\AppProject;
use App\Modules\AppFactory\Infrastructure\Models\AppVersion;
use App\Modules\Branding\Infrastructure\Models\BrandAsset;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;
use App\Modules\Catalog\Infrastructure\Models\Location;
use App\Modules\Scheduling\Infrastructure\Models\LocationSchedule;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the runtime WhiteLabelConfig consumed by the Flutter client at
 * startup (docs/27 §2). Served for EVERY tenant status: suspended and
 * terminated tenants get the minimal payload that drives the courtesy
 * screen instead of a hard error (docs/27 §7).
 */
final readonly class BuildWhiteLabelConfig
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private TemplateRegistry $templates,
    ) {}

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

        $locationModels = Location::query()->where('status', 'active')->orderBy('id')->get();
        $hoursByLocation = $this->openingHoursByLocation($locationModels->pluck('id')->all());

        $locations = $locationModels
            ->map(static fn (Location $l): array => [
                'uuid' => $l->uuid,
                'name' => $l->name,
                'address' => $l->address,
                'phone' => $l->phone,
                'timezone' => $l->timezone,
                'booking_window_days' => $l->booking_window_days,
                'cancellation_cutoff_minutes' => $l->cancellation_cutoff_minutes,
                // weekday (0 = Monday) => [{start, end}] in location local time.
                'opening_hours' => $hoursByLocation[$l->id] ?? new \stdClass,
            ])
            ->all();

        // Template/skin scelto per l'app (App Factory). Tenant-scoped, può
        // essere assente (tenant senza App Project) → il registry dà il default.
        $appProject = AppProject::query()->first();
        $templateCode = $this->templates->has((string) $appProject?->template_code)
            ? (string) $appProject->template_code
            : TemplateRegistry::DEFAULT_CODE;

        // Ultima versione attiva (prep forced-update: il client potrà confrontare
        // il proprio build_number). Tenant-scoped; null se nessuna versione.
        $release = AppVersion::query()->where('status', 'active')->orderByDesc('build_number')->first();

        $payload = [
            'tenant_status' => $tenant->status->value,
            'config_version' => $brand->config_version,
            'app_name' => $brand->app_name,
            'tagline' => $brand->tagline,
            'logo_url' => $this->logoUrl($brand),
            'template' => $templateCode,
            'layout' => $this->templates->layout($templateCode),
            'font_style' => $appProject?->font_style ?? $this->templates->fontStyle($templateCode),
            // Sezioni e ordine del template (data-ready per il rendering dinamico).
            'sections' => $this->templates->sections($templateCode),
            // Release corrente (prep forced-update; il client per ora la ignora).
            'release' => $release === null ? null : [
                'version' => $release->version,
                'build_number' => $release->build_number,
            ],
            'theme' => $brand->theme,
            'locale_default' => $tenant->locale,
            'features' => $tenant->features,
            'booking' => [
                'confirmation_mode' => $tenant->requiresBookingApproval() ? 'request_approve' : 'auto_confirm',
            ],
            // Business contacts shown in the premium "scheda attività". All
            // nullable: the client hides empty entries (no blank rows).
            'contacts' => [
                'phone' => $locationModels->first()?->phone,
                'email' => $brand->contact_email,
                'website' => $brand->website_url,
            ],
            'social' => [
                'instagram_url' => $brand->instagram_url,
                'facebook_url' => $brand->facebook_url,
                'maps_url' => $this->mapsUrl($brand, $locationModels->first()),
                'whatsapp' => $brand->whatsapp_number === null ? null : [
                    'number' => $brand->whatsapp_number,
                    'message' => $brand->whatsapp_message,
                ],
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

    /**
     * @param  list<int>  $locationIds
     * @return array<int, array<int, list<array{start: string, end: string}>>>
     */
    private function openingHoursByLocation(array $locationIds): array
    {
        if ($locationIds === []) {
            return [];
        }

        return LocationSchedule::query()
            ->whereIn('location_id', $locationIds)
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get(['location_id', 'weekday', 'start_time', 'end_time'])
            ->groupBy('location_id')
            ->map(static fn ($rows): array => $rows
                ->groupBy('weekday')
                ->map(static fn ($dayRows): array => $dayRows
                    ->map(static fn ($r): array => [
                        'start' => substr((string) $r->start_time, 0, 5),
                        'end' => substr((string) $r->end_time, 0, 5),
                    ])
                    ->values()
                    ->all())
                ->all())
            ->all();
    }

    private function logoUrl(BrandProfile $brand): ?string
    {
        $logo = BrandAsset::query()
            ->where('brand_profile_id', $brand->id)
            ->where('kind', BrandAsset::KIND_LOGO)
            ->first();

        if ($logo === null) {
            return null;
        }

        return Storage::disk((string) config('branding.asset_disk', 'public'))->url($logo->disk_path);
    }

    /** Explicit maps link, or a Google Maps search derived from the address. */
    private function mapsUrl(BrandProfile $brand, ?Location $primary): ?string
    {
        if ($brand->maps_url !== null) {
            return $brand->maps_url;
        }

        if ($primary?->address === null) {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.urlencode($primary->address);
    }
}
