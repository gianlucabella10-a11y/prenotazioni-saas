<?php

declare(strict_types=1);

namespace App\Modules\Customers\Infrastructure\Models;

use App\Foundation\Tenancy\BelongsToTenant;
use App\Models\User;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The tenant's end customer. May exist without a login (walk-ins, CSV
 * imports): user_id is set once they register on the app.
 */
class Customer extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'marketing_opt_in' => 'bool',
            'last_appointment_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . ($this->last_name ?? ''));
    }

    /** Latest recorded decision for a consent kind (append-only history). */
    public function hasConsent(string $kind): bool
    {
        $latest = $this->consents()
            ->where('kind', $kind)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        return $latest?->granted ?? false;
    }

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }
}
