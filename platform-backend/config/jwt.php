<?php

declare(strict_types=1);

/**
 * JWT configuration (docs/26 §3).
 *
 * Keys are asymmetric (RS256): the private key signs on the API, the public
 * key may be distributed to future first-party verifiers. PEM content is
 * read from files outside the repository or injected base64-encoded via
 * environment (secrets manager in production).
 */
return [
    'algorithm' => 'RS256',

    // Base64-encoded PEM content takes precedence over file paths.
    'private_key_base64' => env('JWT_PRIVATE_KEY_BASE64'),
    'public_key_base64' => env('JWT_PUBLIC_KEY_BASE64'),
    'private_key_path' => env('JWT_PRIVATE_KEY_PATH', storage_path('keys/jwt-private.pem')),
    'public_key_path' => env('JWT_PUBLIC_KEY_PATH', storage_path('keys/jwt-public.pem')),

    // Key id embedded in the token header: enables zero-downtime rotation
    // (two active keys during a transition).
    'key_id' => env('JWT_KEY_ID', 'primary'),

    'access_ttl_seconds' => (int) env('JWT_ACCESS_TTL', 900),          // 15 minutes
    'refresh_ttl_seconds' => (int) env('JWT_REFRESH_TTL', 2592000),    // 30 days
    'mfa_token_ttl_seconds' => (int) env('JWT_MFA_TTL', 300),          // 5 minutes to complete MFA

    'issuer' => env('JWT_ISSUER', env('APP_URL', 'platform')),

    // Clock skew tolerance when validating exp/iat.
    'leeway_seconds' => (int) env('JWT_LEEWAY', 30),
];
