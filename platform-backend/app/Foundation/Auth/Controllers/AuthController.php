<?php

declare(strict_types=1);

namespace App\Foundation\Auth\Controllers;

use App\Foundation\Auth\AuthenticationService;
use App\Foundation\Auth\EmailVerificationService;
use App\Foundation\Auth\JwtGuard;
use App\Foundation\Auth\RefreshTokenService;
use App\Foundation\Http\ApiException;
use App\Foundation\Tenancy\CurrentTenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Authentication endpoints (docs/25 §3, docs/26).
 *
 * register/login run under the tenant key scope (the white label app always
 * knows its tenant); refresh/logout are tenant-independent — the refresh
 * token itself is the credential.
 */
final class AuthController extends Controller
{
    public function register(
        Request $request,
        AuthenticationService $auth,
        EmailVerificationService $verification,
        CurrentTenant $tenant,
    ): JsonResponse {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:10', 'max:255'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', 'string', 'in:it,en'],
            // GDPR (Fase 3): registration requires explicit acceptance.
            'privacy_accepted' => ['required', 'accepted'],
            'privacy_version' => ['nullable', 'string', 'max:32'],
        ]);

        // S1 fix: no CRM customer is created or linked here. The user is
        // born UNVERIFIED; linking happens in EmailVerificationService once
        // ownership of the address is proven.
        $user = $auth->registerCustomer(
            $tenant->id(),
            $data['email'],
            $data['password'],
            $data['locale'] ?? $tenant->get()->locale,
            $data['first_name'],
            $data['last_name'] ?? null,
            $data['phone'] ?? null,
            $data['privacy_version'] ?? null,
        );

        $verification->issue($user);

        $session = $auth->issueSession($user, $request->header('User-Agent'));

        return response()->json($this->sessionPayload($session), 201);
    }

    /** Completes email verification with the 6-digit code (Fase 1). */
    public function verifyEmail(Request $request, EmailVerificationService $verification): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);

        /** @var User $user */
        $user = $request->user();

        $verification->verify($user, $data['code']);

        return response()->json([
            'status' => 'ok',
            'email_verified' => true,
        ]);
    }

    /** Re-sends a fresh verification code, invalidating previous ones. */
    public function resendVerification(Request $request, EmailVerificationService $verification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isEmailVerified()) {
            throw ApiException::unprocessable('already_verified', 'This account is already verified.');
        }

        $verification->issue($user);

        return response()->json(['status' => 'ok']);
    }

    public function login(Request $request, AuthenticationService $auth, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $session = $auth->loginWithPassword(
            $tenant->id(),
            $data['email'],
            $data['password'],
            $request->header('User-Agent'),
        );

        return response()->json($this->sessionPayload($session));
    }

    public function refresh(
        Request $request,
        RefreshTokenService $refreshTokens,
        \App\Foundation\Auth\JwtService $jwt,
    ): JsonResponse {
        $data = $request->validate(['refresh_token' => ['required', 'string']]);

        $rotated = $refreshTokens->rotate($data['refresh_token']);

        return response()->json([
            'access_token' => $jwt->issueAccessToken($rotated['user']),
            'refresh_token' => $rotated['plain'],
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request, RefreshTokenService $refreshTokens): JsonResponse
    {
        $data = $request->validate(['refresh_token' => ['required', 'string']]);

        $refreshTokens->revoke($data['refresh_token']);

        return response()->json(['status' => 'ok']);
    }

    public function mfaVerify(Request $request, AuthenticationService $auth): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);

        $session = $auth->completeMfa($this->mfaScopedUser(), $data['code'], $request->header('User-Agent'));

        return response()->json($this->sessionPayload($session));
    }

    public function mfaSetup(Request $request, AuthenticationService $auth): JsonResponse
    {
        $enrollment = $auth->startMfaEnrollment($this->mfaScopedUser(), (string) config('app.name'));

        return response()->json($enrollment);
    }

    public function mfaConfirm(Request $request, AuthenticationService $auth): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);

        $session = $auth->confirmMfaEnrollment($this->mfaScopedUser(), $data['code'], $request->header('User-Agent'));

        return response()->json($this->sessionPayload($session));
    }

    /** The MFA endpoints accept ONLY scope-mfa tokens (docs/26 §4). */
    private function mfaScopedUser(): User
    {
        $guard = Auth::guard('api');
        $user = $guard->user();
        $claims = $guard instanceof JwtGuard ? $guard->claims() : null;

        if (! $user instanceof User || $claims === null || $claims->isFullyAuthenticated()) {
            throw ApiException::forbidden('mfa_token_required', 'Use the temporary MFA token issued at login.');
        }

        return $user;
    }

    /** @param array{user: User, mfa: string, access_token?: string, refresh_token?: string, mfa_token?: string} $session */
    private function sessionPayload(array $session): array
    {
        $payload = [
            'mfa' => $session['mfa'],
            'user' => [
                'uuid' => $session['user']->uuid,
                'type' => $session['user']->type->value,
                'email' => $session['user']->email,
                'locale' => $session['user']->locale,
                'email_verified' => $session['user']->isEmailVerified(),
            ],
        ];

        if (isset($session['access_token'])) {
            $payload += [
                'access_token' => $session['access_token'],
                'refresh_token' => $session['refresh_token'],
                'token_type' => 'Bearer',
            ];
        }

        if (isset($session['mfa_token'])) {
            $payload['mfa_token'] = $session['mfa_token'];
        }

        return $payload;
    }
}
