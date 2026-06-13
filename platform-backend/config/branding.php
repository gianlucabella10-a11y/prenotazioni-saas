<?php

declare(strict_types=1);

/**
 * White label defaults (docs/27): every new tenant starts from this curated
 * theme token set, replaced during the Brand Studio step of onboarding.
 * Tokens mirror the Flutter design system contract.
 */
return [
    'default_theme' => [
        'colors' => [
            'primary' => '#1F2937',
            'on_primary' => '#FFFFFF',
            'secondary' => '#C8A24B',
            'on_secondary' => '#1F2937',
            'surface' => '#FFFFFF',
            'on_surface' => '#111827',
            'background' => '#F9FAFB',
            'success' => '#15803D',
            'warning' => '#B45309',
            'error' => '#B91C1C',
        ],
        'radius' => [
            'small' => 8,
            'medium' => 12,
            'large' => 24,
        ],
        'typography' => [
            'scale' => 1.0,
        ],
    ],

    // WCAG AA threshold for normal text (docs/05 RF-12).
    'minimum_contrast_ratio' => 4.5,
];
