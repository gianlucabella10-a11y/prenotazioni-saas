<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Beta tester di un tenant (invited/active/blocked). Tenant-scoped. */
class BetaTester extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const STATUSES = ['invited', 'active', 'blocked'];

    protected $guarded = ['id'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
