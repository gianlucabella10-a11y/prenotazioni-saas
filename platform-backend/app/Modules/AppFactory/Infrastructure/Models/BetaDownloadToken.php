<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Token di download privato per un APK (FASE 5). È il segreto nel link: chi lo
 * possiede scarica, finché non è scaduto/revocato/esaurito. Tenant-scoped.
 */
class BetaDownloadToken extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function appBuild(): BelongsTo
    {
        return $this->belongsTo(AppBuild::class);
    }

    /** Il token è valido per il download? */
    public function isDownloadable(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at->isFuture()
            && ($this->max_downloads === null || $this->download_count < $this->max_downloads);
    }
}
