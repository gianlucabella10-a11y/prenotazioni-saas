<?php

declare(strict_types=1);

namespace App\Foundation\Auth;

use App\Foundation\Audit\AuditLogger;
use App\Foundation\Http\ApiException;
use App\Models\User;
use App\Modules\Customers\Infrastructure\Models\Customer;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\DB;

/**
 * Email ownership verification (fix S1 — MVP_PRODUCTION_READINESS_REPORT §6).
 *
 * Security model: registering an address proves NOTHING; entering the
 * 6-digit code delivered to that address proves ownership. Only at that
 * moment the account is linked to a pre-existing CRM customer with the same
 * email (and their appointment history). Until then the customer-surface
 * routes are blocked by the `verified` middleware.
 *
 * Code properties: 6 digits, hashed at rest (sha256 with the user uuid as
 * context), 15-minute expiry, max 5 attempts, single-use, resend
 * invalidates all previous codes.
 */
final readonly class EmailVerificationService
{
    public function __construct(
        private Mailer $mailer,
        private Translator $translator,
        private Config $config,
        private AuditLogger $audit,
    ) {
    }

    public function expiryMinutes(): int
    {
        return (int) $this->config->get('auth_verification.expiry_minutes', 15);
    }

    public function maxAttempts(): int
    {
        return (int) $this->config->get('auth_verification.max_attempts', 5);
    }

    /** Issues a fresh code (invalidating previous ones) and emails it. */
    public function issue(User $user): void
    {
        if ($user->email === null) {
            throw ApiException::unprocessable('email_missing', 'This account has no email address.');
        }

        // Single active code per user: resend = invalidate + reissue.
        DB::table('email_verifications')->where('user_id', $user->id)->delete();

        $code = (string) random_int(100000, 999999);

        DB::table('email_verifications')->insert([
            'user_id' => $user->id,
            'code_hash' => self::hash($code, $user),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($this->expiryMinutes()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->sendCodeEmail($user, $code);
    }

    /**
     * Verifies the code; on success marks the email verified and performs
     * the (now safe) CRM linking.
     *
     * @throws ApiException invalid_verification_code | verification_code_expired |
     *                      too_many_verification_attempts | already_verified
     */
    public function verify(User $user, string $code): void
    {
        if ($user->email_verified_at !== null) {
            throw ApiException::unprocessable('already_verified', 'This account is already verified.');
        }

        $record = DB::table('email_verifications')
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if ($record === null) {
            throw ApiException::unauthorized('invalid_verification_code', 'Request a new verification code.');
        }

        if (now()->greaterThan($record->expires_at)) {
            throw ApiException::unauthorized('verification_code_expired', 'The verification code has expired. Request a new one.');
        }

        if ($record->attempts >= $this->maxAttempts()) {
            throw ApiException::unauthorized('too_many_verification_attempts', 'Too many attempts. Request a new verification code.');
        }

        if (! hash_equals($record->code_hash, self::hash($code, $user))) {
            DB::table('email_verifications')->where('id', $record->id)->increment('attempts');

            throw ApiException::unauthorized('invalid_verification_code', 'The verification code is not valid.');
        }

        DB::transaction(function () use ($user, $record): void {
            // Single-use: a second verify with the same code must fail.
            $claimed = DB::table('email_verifications')
                ->where('id', $record->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            if ($claimed === 0) {
                throw ApiException::unauthorized('invalid_verification_code', 'The verification code is not valid.');
            }

            $user->forceFill(['email_verified_at' => now()])->save();

            $this->linkOrCreateCustomer($user);
        });

        $this->audit->log('auth.email_verified', $user->id, [], $user->tenant_id);
    }

    /**
     * The S1 fix lives here: linking to an existing CRM record (and its
     * appointment history) happens ONLY after ownership is proven.
     */
    private function linkOrCreateCustomer(User $user): void
    {
        /** @var Customer|null $existing */
        $existing = Customer::query()
            ->whereNull('user_id')
            ->where('email', $user->email)
            ->first();

        if ($existing !== null) {
            $existing->update(['user_id' => $user->id]);

            return;
        }

        if (Customer::query()->where('user_id', $user->id)->exists()) {
            return; // idempotency guard
        }

        Customer::query()->create([
            'user_id' => $user->id,
            'first_name' => $user->first_name ?? '',
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'source' => 'app',
        ]);
    }

    private function sendCodeEmail(User $user, string $code): void
    {
        $locale = $user->locale ?: 'it';

        $subject = (string) $this->translator->get('verification.subject', [], $locale);
        $body = (string) $this->translator->get('verification.body', [
            'code' => $code,
            'minutes' => $this->expiryMinutes(),
        ], $locale);

        // Sent directly (not through the outbox): the clear code must never
        // be persisted anywhere — only its hash is.
        $this->mailer->raw($body, function ($mail) use ($user, $subject): void {
            $mail->to($user->email)->subject($subject);
        });
    }

    private static function hash(string $code, User $user): string
    {
        return hash('sha256', $code . $user->uuid);
    }
}
