<?php

declare(strict_types=1);

namespace App\Foundation\Auth;

use App\Foundation\Http\ApiException;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opaque refresh tokens with rotation and family-reuse detection
 * (docs/26 §3): every refresh issues a new token in the same family; the
 * reuse of an already-rotated token is treated as theft and revokes the
 * entire family, forcing re-login.
 */
final class RefreshTokenService
{
    private const TOKEN_BYTES = 32;

    public function __construct(private readonly Config $config)
    {
    }

    /** @return array{plain: string, model: RefreshToken} */
    public function issue(User $user, ?string $deviceLabel = null, ?string $familyUuid = null, ?int $rotatedFromId = null): array
    {
        $plain = bin2hex(random_bytes(self::TOKEN_BYTES));

        $model = $user->refreshTokens()->create([
            'token_hash' => self::hash($plain),
            'family_uuid' => $familyUuid ?? (string) Str::uuid(),
            'device_label' => $deviceLabel,
            'expires_at' => now()->addSeconds((int) $this->config->get('jwt.refresh_ttl_seconds')),
            'rotated_from_id' => $rotatedFromId,
        ]);

        return ['plain' => $plain, 'model' => $model];
    }

    /**
     * Rotate a presented refresh token.
     *
     * The theft-response revocation runs OUTSIDE the rotation transaction:
     * a thrown ApiException must never roll back the family revocation
     * (the security action has to persist precisely when we reject).
     *
     * @return array{plain: string, model: RefreshToken, user: User}
     *
     * @throws ApiException 401 invalid_refresh_token | refresh_token_reused
     */
    public function rotate(string $plainToken): array
    {
        /** @var RefreshToken|null $current */
        $current = RefreshToken::query()->where('token_hash', self::hash($plainToken))->first();

        if ($current === null) {
            throw ApiException::unauthorized('invalid_refresh_token', 'The refresh token is not recognized.');
        }

        if ($current->revoked_at !== null) {
            $this->punishReuse($current);
        }

        if ($current->expires_at->isPast()) {
            throw ApiException::unauthorized('invalid_refresh_token', 'The refresh token is expired.');
        }

        $rotated = DB::transaction(function () use ($current): ?array {
            /** @var RefreshToken $locked */
            $locked = RefreshToken::query()->whereKey($current->id)->lockForUpdate()->firstOrFail();

            if ($locked->revoked_at !== null) {
                return null; // lost a concurrent rotation race: treat as reuse
            }

            $locked->forceFill(['revoked_at' => now()])->save();

            $user = $locked->user()->firstOrFail();

            return $this->issue($user, $locked->device_label, $locked->family_uuid, $locked->id) + ['user' => $user];
        });

        if ($rotated === null) {
            $this->punishReuse($current);
        }

        return $rotated;
    }

    /** @throws ApiException always: revokes the family, then rejects. */
    private function punishReuse(RefreshToken $token): never
    {
        $this->revokeFamily($token->family_uuid);

        throw ApiException::unauthorized(
            'refresh_token_reused',
            'This refresh token was already used. All sessions of this family have been revoked.'
        );
    }

    public function revoke(string $plainToken): void
    {
        RefreshToken::query()
            ->where('token_hash', self::hash($plainToken))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeFamily(string $familyUuid): void
    {
        RefreshToken::query()
            ->where('family_uuid', $familyUuid)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /** Revoke every session, e.g. on password change or account compromise. */
    public function revokeAllFor(User $user): void
    {
        $user->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    private static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
