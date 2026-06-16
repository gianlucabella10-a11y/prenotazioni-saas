<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use App\Modules\AppFactory\Domain\AppProjectStatus;
use Database\Factories\AppProjectFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Store/build identity of a tenant's white-label app (1:1 con il tenant).
 * Tenant-scoped come BrandProfile: in contesto tenant `query()->first()`
 * restituisce il progetto del tenant corrente; lato Control Room si legge
 * via CurrentTenant::bypass.
 */
class AppProject extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AppProjectFactory> */
    use HasFactory;

    use HasUuids;

    protected $guarded = ['id'];

    /** Identità store/build: unica e IMMUTABILE dopo la creazione (docs/27 §3). */
    private const IMMUTABLE = ['uuid', 'tenant_id', 'bundle_id', 'package_name', 'shortcode'];

    protected static function booted(): void
    {
        // Blocca ogni mutazione dell'identità su un progetto già esistente:
        // template/font/build_status restano modificabili, l'identità no.
        static::updating(function (self $project): void {
            foreach (self::IMMUTABLE as $field) {
                if ($project->isDirty($field)) {
                    throw new \LogicException("AppProject.{$field} è immutabile dopo la creazione.");
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'build_status' => AppProjectStatus::class,
            'build_manifest' => 'array',
            'powered_by_enabled' => 'bool',
            'last_generated_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function builds(): HasMany
    {
        return $this->hasMany(AppBuild::class);
    }

    protected static function newFactory(): AppProjectFactory
    {
        return AppProjectFactory::new();
    }
}
