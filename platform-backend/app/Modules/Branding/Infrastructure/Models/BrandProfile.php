<?php

declare(strict_types=1);

namespace App\Modules\Branding\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use Database\Factories\BrandProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The tenant's white label identity. config_version is bumped on every
 * change and drives ETag-based cache busting of the runtime
 * WhiteLabelConfig (docs/27 §2).
 */
class BrandProfile extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<BrandProfileFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'theme' => 'array',
            'contrast_validated' => 'bool',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(BrandAsset::class);
    }

    public function bumpConfigVersion(): void
    {
        $this->increment('config_version');
    }

    protected static function newFactory(): BrandProfileFactory
    {
        return BrandProfileFactory::new();
    }
}
