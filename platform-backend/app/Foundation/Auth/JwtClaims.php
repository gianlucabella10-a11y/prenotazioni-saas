<?php

declare(strict_types=1);

namespace App\Foundation\Auth;

use App\Foundation\Enums\UserType;

/**
 * Validated claim set of an accepted access token.
 *
 * `tenantId` is the internal numeric id: the token is signed, so the value
 * cannot be tampered with, and it is never exposed through API responses
 * (resources always serialize uuids).
 */
final readonly class JwtClaims
{
    public const SCOPE_FULL = 'full';
    public const SCOPE_MFA = 'mfa';

    public function __construct(
        public string $userUuid,
        public ?int $tenantId,
        public UserType $userType,
        public string $scope,
        public string $tokenId,
        public int $expiresAt,
    ) {
    }

    public function isFullyAuthenticated(): bool
    {
        return $this->scope === self::SCOPE_FULL;
    }
}
