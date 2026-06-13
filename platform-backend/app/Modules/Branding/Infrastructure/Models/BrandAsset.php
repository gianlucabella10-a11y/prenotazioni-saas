<?php

declare(strict_types=1);

namespace App\Modules\Branding\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One uploaded branding asset (logo, icon source, splash source…), stored
 * on the object storage disk and referenced by path. Tenant scoping is
 * inherited through the parent BrandProfile.
 */
class BrandAsset extends Model
{
    public const KIND_LOGO = 'logo';
    public const KIND_ICON_SOURCE = 'icon_source';
    public const KIND_SPLASH_SOURCE = 'splash_source';

    protected $guarded = ['id'];

    public function brandProfile(): BelongsTo
    {
        return $this->belongsTo(BrandProfile::class);
    }
}
