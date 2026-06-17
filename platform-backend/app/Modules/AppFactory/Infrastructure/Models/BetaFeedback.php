<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Segnalazione di un beta tester (tenant-scoped). */
class BetaFeedback extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'beta_feedback';

    protected $guarded = ['id'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
