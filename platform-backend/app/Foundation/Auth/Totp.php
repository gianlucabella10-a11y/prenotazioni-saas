<?php

declare(strict_types=1);

namespace App\Foundation\Auth;

/**
 * RFC 6238 TOTP (SHA-1, 6 digits, 30s period — the interoperable profile
 * supported by every authenticator app).
 *
 * Pure and framework-free: fully unit-testable against RFC test vectors.
 */
final class Totp
{
    private const PERIOD_SECONDS = 30;
    private const DIGITS = 6;
    private const SECRET_BYTES = 20;
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Generate a new random secret, base32-encoded for authenticator apps. */
    public function generateSecret(): string
    {
        return self::base32Encode(random_bytes(self::SECRET_BYTES));
    }

    /**
     * Verify a user-supplied code, accepting ±$window periods of clock
     * drift. Comparison is constant-time.
     */
    public function verify(string $secret, string $code, ?int $timestamp = null, int $window = 1): bool
    {
        $timestamp ??= time();
        $counter = intdiv($timestamp, self::PERIOD_SECONDS);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->codeForCounter($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /** otpauth:// URI consumed by authenticator apps via QR code. */
    public function provisioningUri(string $secret, string $accountLabel, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($accountLabel),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD_SECONDS,
        );
    }

    public function codeForCounter(string $secret, int $counter): string
    {
        $binaryCounter = pack('J', $counter); // 64-bit big-endian
        $hash = hash_hmac('sha1', $binaryCounter, self::base32Decode($secret), true);

        $offset = ord($hash[19]) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3])
        ) % (10 ** self::DIGITS);

        return str_pad((string) $truncated, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $binary): string
    {
        $bits = '';

        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    private static function base32Decode(string $encoded): string
    {
        $bits = '';

        foreach (str_split(strtoupper($encoded)) as $char) {
            $index = strpos(self::BASE32_ALPHABET, $char);

            if ($index === false) {
                continue; // tolerate padding/separators
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr((int) bindec($chunk));
            }
        }

        return $binary;
    }
}
