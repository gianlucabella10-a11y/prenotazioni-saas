<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Models;

use App\Modules\TenantManagement\Domain\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Platform-level model: deliberately NOT tenant-bound (it IS the tenant).
 *
 * @property int $id
 * @property string $uuid
 * @property TenantStatus $status
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'onboarding_state' => 'array',
            'settings' => 'array',
            'health_data_enabled' => 'bool',
            'suspended_at' => 'datetime',
            'terminated_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->whereIn('status', ['trialing', 'active', 'past_due'])->latest('id');
    }

    public function featureOverrides(): HasMany
    {
        return $this->hasMany(TenantFeature::class);
    }

    /**
     * Plain-array snapshot cached by TenantRegistry (no Eloquent in Redis).
     *
     * @return array<string, mixed>
     */
    public function toContextSnapshot(): array
    {
        $planFeatures = $this->activeSubscription?->plan?->features ?? [];

        $overrides = $this->featureOverrides
            ->mapWithKeys(fn (TenantFeature $f): array => [$f->feature_code => $f->enabled])
            ->all();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'timezone' => $this->default_timezone,
            'locale' => $this->default_locale,
            'health_data_enabled' => $this->health_data_enabled,
            'features' => array_merge($planFeatures, $overrides),
            'settings' => $this->settings ?? [],
        ];
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }
}
