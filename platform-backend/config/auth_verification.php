<?php

declare(strict_types=1);

/** Email verification tuning (Fase 1 — beta readiness). */
return [
    'expiry_minutes' => (int) env('VERIFICATION_EXPIRY_MINUTES', 15),
    'max_attempts' => (int) env('VERIFICATION_MAX_ATTEMPTS', 5),
];
