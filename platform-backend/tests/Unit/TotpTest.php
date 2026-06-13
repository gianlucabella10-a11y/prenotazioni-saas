<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Foundation\Auth\Totp;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    private Totp $totp;

    protected function setUp(): void
    {
        $this->totp = new Totp();
    }

    /**
     * RFC 6238 Appendix B test vectors (SHA-1, secret "12345678901234567890"
     * = base32 GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ), truncated to 6 digits.
     */
    public function test_rfc6238_vectors(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $vectors = [
            59 => '287082',
            1111111109 => '081804',
            1234567890 => '005924',
            2000000000 => '279037',
        ];

        foreach ($vectors as $timestamp => $expected) {
            self::assertTrue(
                $this->totp->verify($secret, $expected, $timestamp, 0),
                "vector at t={$timestamp}",
            );
        }
    }

    public function test_rejects_wrong_code(): void
    {
        $secret = $this->totp->generateSecret();

        self::assertFalse($this->totp->verify($secret, '000000', 59, 0));
    }

    public function test_window_tolerates_clock_drift(): void
    {
        $secret = $this->totp->generateSecret();
        $code = $this->totp->codeForCounter($secret, intdiv(1000000, 30));

        self::assertTrue($this->totp->verify($secret, $code, 1000000 + 30, 1));  // one period late
        self::assertFalse($this->totp->verify($secret, $code, 1000000 + 90, 1)); // three periods late
    }

    public function test_generated_secret_is_base32(): void
    {
        $secret = $this->totp->generateSecret();

        self::assertSame(32, strlen($secret)); // 20 bytes -> 32 base32 chars
        self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function test_provisioning_uri_format(): void
    {
        $uri = $this->totp->provisioningUri('ABCDEF234567', 'admin@salone.it', 'Piattaforma');

        self::assertStringStartsWith('otpauth://totp/Piattaforma:admin%40salone.it?', $uri);
        self::assertStringContainsString('secret=ABCDEF234567', $uri);
        self::assertStringContainsString('issuer=Piattaforma', $uri);
    }
}
