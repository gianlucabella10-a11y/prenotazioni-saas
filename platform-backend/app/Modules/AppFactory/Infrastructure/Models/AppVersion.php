<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Versione rilasciata di un App Project (active/deprecated). Tenant-scoped. */
class AppVersion extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const STATUSES = ['active', 'deprecated'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['build_number' => 'integer'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
