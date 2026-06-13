<?php

declare(strict_types=1);

/**
 * API surface tuning (docs/25 §6). Limits are per minute.
 */
return [
    'rate_limits' => [
        'auth_per_ip' => (int) env('RATE_LIMIT_AUTH', 5),
        'availability_per_user' => (int) env('RATE_LIMIT_AVAILABILITY', 60),
        'default_per_user' => (int) env('RATE_LIMIT_DEFAULT', 120),
        'default_per_ip' => (int) env('RATE_LIMIT_ANON', 60),
        'tenant_ceiling' => (int) env('RATE_LIMIT_TENANT', 1000),
    ],
];
