<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCategory extends Model
{
    use BelongsToTenant;
    use HasUuids;
    use SoftDeletes;

    protected $guarded = ['id'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }
}
