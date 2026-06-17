<?php

declare(strict_types=1);

namespace App\Modules\AppFactory\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One generation/build attempt of an App Project (FASE 1: platform=config). */
class AppBuild extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function appProject(): BelongsTo
    {
        return $this->belongsTo(AppProject::class);
    }
}
