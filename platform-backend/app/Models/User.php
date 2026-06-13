<?php

declare(strict_types=1);

namespace App\Models;

use App\Foundation\Enums\UserType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Modules\TenantManagement\Infrastructure\Models\Tenant;

/**
 * Every authenticatable identity (super admin, tenant admin, staff,
 * customer). Tenant-partitioned via tenant_id but intentionally NOT using
 * BelongsToTenant: authentication happens before a tenant context exists,
 * so scoping is enforced by the auth layer itself (guard + queries always
 * filter by tenant where applicable).
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $tenant_id
 * @property UserType $type
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            'password' => 'hashed',
            'mfa_enforced' => 'bool',
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function mfaCredentials(): HasMany
    {
        return $this->hasMany(MfaCredential::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function confirmedMfaCredential(): ?MfaCredential
    {
        return $this->mfaCredentials->first(fn (MfaCredential $c): bool => $c->confirmed_at !== null);
    }

    /** MFA is pending when enforced and a confirmed credential exists. */
    public function requiresMfaVerification(): bool
    {
        return $this->mfa_enforced && $this->confirmedMfaCredential() !== null;
    }

    /** MFA is enforced but never configured: user must complete setup. */
    public function requiresMfaSetup(): bool
    {
        return $this->mfa_enforced && $this->confirmedMfaCredential() === null;
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
