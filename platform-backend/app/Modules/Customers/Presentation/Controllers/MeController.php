<?php

declare(strict_types=1);

namespace App\Modules\Customers\Presentation\Controllers;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Auth\RefreshTokenService;
use App\Models\User;
use App\Modules\Customers\Infrastructure\Models\Consent;
use App\Modules\Customers\Infrastructure\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Minimal account management for the customer (Fase 2 — beta readiness):
 * profile read/update, marketing consents, Apple-compliant account
 * deletion (guideline 5.1.1(v)): immediate, in-app, irreversible for the
 * user, with pseudonymization that preserves the tenant's business records
 * (docs/33 #53).
 */
final class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->serialize($user)]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $user->update($data);

        // Keep the CRM record (created at verification) in sync.
        Customer::query()->where('user_id', $user->id)->first()?->update($data);

        return response()->json(['data' => $this->serialize($user->refresh())]);
    }

    /** Appends marketing consent decisions (history is append-only, GDPR). */
    public function updateConsents(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'marketing_push' => ['sometimes', 'boolean'],
            'marketing_email' => ['sometimes', 'boolean'],
        ]);

        $customer = Customer::query()->where('user_id', $user->id)->first();

        $kinds = [
            'marketing_push' => Consent::KIND_MARKETING_PUSH,
            'marketing_email' => Consent::KIND_MARKETING_EMAIL,
        ];

        foreach ($kinds as $field => $kind) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            Consent::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'customer_id' => $customer?->id,
                'kind' => $kind,
                'granted' => (bool) $data[$field],
                'source' => 'app_settings',
                'occurred_at' => now(),
            ]);
        }

        if ($customer !== null && array_key_exists('marketing_push', $data)) {
            $customer->update(['marketing_opt_in' => (bool) $data['marketing_push']]);
        }

        return response()->json(['data' => $this->serialize($user)]);
    }

    /**
     * Apple-compliant in-app account deletion: revokes every session,
     * removes devices and MFA, pseudonymizes user + CRM record, deletes
     * personal notes. Appointment rows survive pseudonymized (tenant
     * business/fiscal records). Fully audit-logged.
     */
    public function destroy(
        Request $request,
        RefreshTokenService $refreshTokens,
        AuditLogger $audit,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($user, $refreshTokens): void {
            $refreshTokens->revokeAllFor($user);

            $user->devices()->delete();
            $user->mfaCredentials()->delete();

            $customer = Customer::query()->where('user_id', $user->id)->first();

            if ($customer !== null) {
                $customer->notes()->delete();

                $customer->update([
                    'user_id' => null,
                    'first_name' => 'Account eliminato',
                    'last_name' => null,
                    'email' => null,
                    'phone' => null,
                    'birthdate' => null,
                    'marketing_opt_in' => false,
                ]);
            }

            $user->forceFill([
                'status' => 'deleted',
                'email' => null,
                'phone' => null,
                'password' => null,
                'social_provider' => null,
                'social_id' => null,
                'first_name' => null,
                'last_name' => null,
                'email_verified_at' => null,
                'remember_token' => null,
            ])->save();
        });

        $audit->log('account.deleted', $user->id, [], $user->tenant_id, User::class, $user->id);

        return response()->json(['status' => 'deleted']);
    }

    /** @return array<string, mixed> */
    private function serialize(User $user): array
    {
        return [
            'uuid' => $user->uuid,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'email_verified' => $user->isEmailVerified(),
        ];
    }
}
