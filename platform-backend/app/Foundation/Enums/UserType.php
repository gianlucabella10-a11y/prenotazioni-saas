<?php

declare(strict_types=1);

namespace App\Foundation\Enums;

enum UserType: string
{
    case SuperAdmin = 'super_admin';
    case TenantAdmin = 'tenant_admin';
    case Staff = 'staff';
    case Customer = 'customer';

    /** Platform-level users have no tenant. */
    public function isPlatform(): bool
    {
        return $this === self::SuperAdmin;
    }

    /** Users allowed on the /manage API surface. */
    public function canManageTenant(): bool
    {
        return $this === self::TenantAdmin || $this === self::Staff;
    }

    /** Users with full tenant configuration rights. */
    public function isTenantAdmin(): bool
    {
        return $this === self::TenantAdmin;
    }
}
