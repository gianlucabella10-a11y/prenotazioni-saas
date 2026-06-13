<?php

declare(strict_types=1);

namespace App\Foundation\Auth;

use App\Foundation\Enums\UserType;
use App\Foundation\Http\ApiException;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Issues and validates the platform's access tokens (docs/26 §3).
 *
 * Asymmetric signing (RS256): tokens are short-lived (15 min default);
 * revocation runs through refresh tokens, with token-family reuse detection
 * in RefreshTokenService.
 */
final class JwtService
{
    public function __construct(private readonly Config $config)
    {
    }

    public function issueAccessToken(User $user, string $scope = JwtClaims::SCOPE_FULL): string
    {
        $ttl = $scope === JwtClaims::SCOPE_MFA
            ? (int) $this->config->get('jwt.mfa_token_ttl_seconds')
            : (int) $this->config->get('jwt.access_ttl_seconds');

        $now = time();

        $payload = [
            'iss' => $this->config->get('jwt.issuer'),
            'sub' => $user->uuid,
            'tid' => $user->tenant_id,
            'typ' => $user->type->value,
            'scope' => $scope,
            'jti' => (string) Str::uuid(),
            'iat' => $now,
            'exp' => $now + $ttl,
        ];

        return JWT::encode(
            $payload,
            $this->privateKey(),
            (string) $this->config->get('jwt.algorithm'),
            (string) $this->config->get('jwt.key_id'),
        );
    }

    /** @throws ApiException 401 on any structurally or cryptographically invalid token */
    public function validate(string $token): JwtClaims
    {
        JWT::$leeway = (int) $this->config->get('jwt.leeway_seconds');

        try {
            $decoded = JWT::decode(
                $token,
                new Key($this->publicKey(), (string) $this->config->get('jwt.algorithm')),
            );
        } catch (Throwable) {
            throw ApiException::unauthorized('invalid_token', 'The access token is invalid or expired.');
        }

        $type = UserType::tryFrom((string) ($decoded->typ ?? ''));

        if ($type === null || ! isset($decoded->sub, $decoded->scope, $decoded->jti, $decoded->exp)) {
            throw ApiException::unauthorized('invalid_token', 'The access token is malformed.');
        }

        return new JwtClaims(
            userUuid: (string) $decoded->sub,
            tenantId: isset($decoded->tid) ? (int) $decoded->tid : null,
            userType: $type,
            scope: (string) $decoded->scope,
            tokenId: (string) $decoded->jti,
            expiresAt: (int) $decoded->exp,
        );
    }

    private function privateKey(): string
    {
        return $this->keyMaterial('private');
    }

    private function publicKey(): string
    {
        return $this->keyMaterial('public');
    }

    private function keyMaterial(string $which): string
    {
        $base64 = $this->config->get("jwt.{$which}_key_base64");

        if (is_string($base64) && $base64 !== '') {
            $decoded = base64_decode($base64, true);

            if ($decoded === false) {
                throw new RuntimeException("JWT {$which} key is not valid base64.");
            }

            return $decoded;
        }

        $path = (string) $this->config->get("jwt.{$which}_key_path");

        if (! is_file($path)) {
            throw new RuntimeException(
                "JWT {$which} key not found at {$path}. Run `php artisan jwt:generate-keys` or set JWT_*_KEY_BASE64."
            );
        }

        return (string) file_get_contents($path);
    }
}
