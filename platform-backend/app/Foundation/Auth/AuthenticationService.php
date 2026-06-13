<?php

declare(strict_types=1);

namespace App\Foundation\Auth;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Enums\UserType;
use App\Foundation\Http\ApiException;
use App\Models\MfaCredential;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Credential verification and token issuance for every login flow
 * (docs/26 §2-4). The guard authenticates requests; this service
 * authenticates people.
 */
final readonly class AuthenticationService
{
    public function __construct(
        private JwtService $jwt,
        private RefreshTokenService $refreshTokens,
        private Totp $totp,
        private AuditLogger $audit,
    ) {
    }

    /**
     * Password login for a user already resolved to a tenant scope.
     *
     * @return array{user: User, access_token?: string, refresh_token?: string, mfa_token?: string, mfa: string}
     */
    public function loginWithPassword(?int $tenantId, string $email, string $password, ?string $deviceLabel = null): array
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('tenant_id', $tenantId)
            ->where('email', mb_strtolower(trim($email)))
            ->first();

        if ($user === null || $user->password === null || ! Hash::check($password, $user->password)) {
            $this->audit->log('auth.login_failed', null, ['email_domain' => self::emailDomain($email)], $tenantId);

            throw ApiException::unauthorized('invalid_credentials', 'Email or password is incorrect.');
        }

        if ($user->status !== 'active') {
            throw ApiException::forbidden('account_disabled', 'This account is disabled.');
        }

        if ($user->requiresMfaSetup()) {
            return [
                'user' => $user,
                'mfa' => 'setup_required',
                'mfa_token' => $this->jwt->issueAccessToken($user, JwtClaims::SCOPE_MFA),
            ];
        }

        if ($user->requiresMfaVerification()) {
            return [
                'user' => $user,
                'mfa' => 'verification_required',
                'mfa_token' => $this->jwt->issueAccessToken($user, JwtClaims::SCOPE_MFA),
            ];
        }

        return $this->issueSession($user, $deviceLabel);
    }

    /**
     * Complete an MFA challenge: exchanges a scope-mfa token + TOTP code for
     * a full session.
     *
     * @return array{user: User, access_token: string, refresh_token: string, mfa: string}
     */
    public function completeMfa(User $user, string $code, ?string $deviceLabel = null): array
    {
        $credential = $user->confirmedMfaCredential();

        if ($credential === null) {
            throw ApiException::forbidden('mfa_not_configured', 'MFA is not configured for this account.');
        }

        if (! $this->totp->verify($credential->secret, $code)) {
            $this->audit->log('auth.mfa_failed', $user->id, [], $user->tenant_id);

            throw ApiException::unauthorized('invalid_mfa_code', 'The verification code is not valid.');
        }

        return $this->issueSession($user, $deviceLabel);
    }

    /**
     * Begin TOTP enrollment: stores an unconfirmed secret and returns the
     * provisioning URI for the authenticator app.
     *
     * @return array{secret: string, provisioning_uri: string}
     */
    public function startMfaEnrollment(User $user, string $issuerName): array
    {
        $secret = $this->totp->generateSecret();

        $user->mfaCredentials()->updateOrCreate(
            ['type' => MfaCredential::TYPE_TOTP],
            ['secret' => $secret, 'confirmed_at' => null],
        );

        return [
            'secret' => $secret,
            'provisioning_uri' => $this->totp->provisioningUri($secret, $user->email ?? $user->uuid, $issuerName),
        ];
    }

    /**
     * Confirm enrollment with a first valid code, then issue a full session.
     *
     * @return array{user: User, access_token: string, refresh_token: string, mfa: string}
     */
    public function confirmMfaEnrollment(User $user, string $code, ?string $deviceLabel = null): array
    {
        $credential = $user->mfaCredentials()->where('type', MfaCredential::TYPE_TOTP)->first();

        if ($credential === null || ! $this->totp->verify($credential->secret, $code)) {
            throw ApiException::unauthorized('invalid_mfa_code', 'The verification code is not valid.');
        }

        $credential->forceFill(['confirmed_at' => now()])->save();

        $this->audit->log('auth.mfa_enrolled', $user->id, [], $user->tenant_id);

        return $this->issueSession($user, $deviceLabel);
    }

    /** @return array{user: User, access_token: string, refresh_token: string, mfa: string} */
    public function issueSession(User $user, ?string $deviceLabel = null): array
    {
        $user->forceFill(['last_login_at' => now()])->save();

        $refresh = $this->refreshTokens->issue($user, $deviceLabel);

        $this->audit->log('auth.login', $user->id, ['type' => $user->type->value], $user->tenant_id);

        return [
            'user' => $user,
            'mfa' => 'none',
            'access_token' => $this->jwt->issueAccessToken($user),
            'refresh_token' => $refresh['plain'],
        ];
    }

    /**
     * Registration of a new end-customer identity within the current tenant.
     *
     * SECURITY (S1 fix): registration creates an UNVERIFIED user only.
     * No customer record is created or linked here — that happens in
     * EmailVerificationService::verify, once email ownership is proven.
     * The privacy consent (timestamp + document version) is recorded at
     * user level immediately (GDPR, Fase 3).
     */
    public function registerCustomer(
        int $tenantId,
        string $email,
        string $password,
        string $locale,
        string $firstName,
        ?string $lastName = null,
        ?string $phone = null,
        ?string $privacyDocumentVersion = null,
    ): User {
        $email = mb_strtolower(trim($email));

        $exists = User::query()
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->exists();

        if ($exists) {
            throw ApiException::conflict('email_taken', 'An account with this email already exists.');
        }

        try {
            $user = User::query()->create([
                'tenant_id' => $tenantId,
                'type' => UserType::Customer,
                'email' => $email,
                'password' => $password, // hashed cast
                'locale' => $locale,
                'status' => 'active',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone !== null && $phone !== '' ? $phone : null,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Concurrent duplicate registration: the DB unique index is the
            // real guard, the pre-check above only gives the nicer error.
            throw ApiException::conflict('email_taken', 'An account with this email already exists.');
        }

        \App\Modules\Customers\Infrastructure\Models\Consent::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'kind' => \App\Modules\Customers\Infrastructure\Models\Consent::KIND_PRIVACY_POLICY,
            'granted' => true,
            'source' => 'registration',
            'document_version' => $privacyDocumentVersion,
            'occurred_at' => now(),
        ]);

        return $user;
    }

    private static function emailDomain(string $email): string
    {
        $at = strrchr($email, '@');

        return $at === false ? 'invalid' : substr($at, 1);
    }
}
