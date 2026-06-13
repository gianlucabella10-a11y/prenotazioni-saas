<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Process-wide ephemeral RS256 keypair: generated once, reused by all tests. */
    private static ?array $jwtKeys = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureTestJwtKeys();
    }

    private function configureTestJwtKeys(): void
    {
        if (self::$jwtKeys === null) {
            $resource = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            openssl_pkey_export($resource, $privatePem);

            self::$jwtKeys = [
                'private' => $privatePem,
                'public' => openssl_pkey_get_details($resource)['key'],
            ];
        }

        config()->set('jwt.private_key_base64', base64_encode(self::$jwtKeys['private']));
        config()->set('jwt.public_key_base64', base64_encode(self::$jwtKeys['public']));
    }
}
