<?php

declare(strict_types=1);

namespace App\Modules\Staff\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use App\Models\User;
use Database\Factories\StaffMemberFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Catalog\Infrastructure\Models\Service;
use App\Modules\Scheduling\Infrastructure\Models\StaffSchedule;

/**
 * A person who performs services. May or may not have a login (user_id):
 * imported or non-operating staff exist as agenda resources only.
 */
class StaffMember extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<StaffMemberFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_bookable' => 'bool'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'staff_services');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(StaffSchedule::class);
    }

    protected static function newFactory(): StaffMemberFactory
    {
        return StaffMemberFactory::new();
    }
}
