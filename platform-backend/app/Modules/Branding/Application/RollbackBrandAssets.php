<?php

declare(strict_types=1);

namespace App\Modules\Branding\Application;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Tenancy\CurrentTenant;
use App\Modules\Branding\Infrastructure\Models\BrandAsset;
use App\Modules\Branding\Infrastructure\Models\BrandProfile;

/**
 * Rollback dell'Asset Factory: rende "corrente" una versione precedente dei
 * derivati (icone/splash/store) senza rigenerarli — lo storico resta intatto.
 * Dopo il rollback occorre rigenerare il pacchetto perché il manifest punti
 * alla versione ripristinata. Graceful: false se la versione non esiste.
 */
final readonly class RollbackBrandAssets
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private AuditLogger $audit,
    ) {}

    public function execute(int $tenantId, int $version, ?int $actorUserId = null): bool
    {
        return $this->currentTenant->bypass(function () use ($tenantId, $version, $actorUserId): bool {
            $brand = BrandProfile::query()->where('tenant_id', $tenantId)->first();

            if ($brand === null) {
                return false;
            }

            $base = BrandAsset::query()
                ->where('brand_profile_id', $brand->id)
                ->whereNotNull('variant')
                ->where('version', $version);

            if (! (clone $base)->exists()) {
                return false; // versione inesistente
            }

            BrandAsset::query()
                ->where('brand_profile_id', $brand->id)
                ->whereNotNull('variant')
                ->update(['is_current' => false]);

            (clone $base)->update(['is_current' => true]);

            $this->audit->log('brand_assets.rolled_back', $actorUserId, ['version' => $version], $tenantId);

            return true;
        });
    }

    /**
     * Versioni disponibili (desc) con conteggio derivati e flag corrente.
     *
     * @return list<array{version:int, count:int, current:bool}>
     */
    public function versions(int $tenantId): array
    {
        return $this->currentTenant->bypass(function () use ($tenantId): array {
            $brand = BrandProfile::query()->where('tenant_id', $tenantId)->first();

            if ($brand === null) {
                return [];
            }

            return BrandAsset::query()
                ->where('brand_profile_id', $brand->id)
                ->whereNotNull('variant')
                ->selectRaw('version, count(*) as c, max(is_current) as cur')
                ->groupBy('version')
                ->orderByDesc('version')
                ->get()
                ->map(fn ($r): array => [
                    'version' => (int) $r->version,
                    'count' => (int) $r->c,
                    'current' => (bool) $r->cur,
                ])
                ->all();
        });
    }
}
