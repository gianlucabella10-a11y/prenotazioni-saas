<?php

declare(strict_types=1);

/**
 * Template white-label CURATI dalla piattaforma (App Factory FASE 1).
 * Sono SKIN: cambiano tema, font e variante di layout — il motore
 * (prenotazioni, auth, account, backend) resta identico. Il tenant SCEGLIE
 * un template, non lo progetta (guardrail qualità). Stesso pattern di
 * sector_presets.php. In FASE 3 potranno passare su DB con editor.
 *
 * Chiavi per template:
 *  - label, vertical
 *  - theme: token (colors/radius/typography) coerenti col design system
 *  - font_style: famiglia da whitelist impacchettata (consumata in FASE 2)
 *  - layout_variant: quale layout Home/Scheda usa l'app (consumato in FASE 2)
 *  - sections: sezioni mostrate e ordine
 */
return [
    'default' => [
        'label' => 'Standard',
        'vertical' => 'other',
        'theme' => [
            'colors' => ['primary' => '#1F2937', 'secondary' => '#C8A24B'],
            'radius' => ['small' => 8, 'medium' => 12, 'large' => 24],
            'typography' => ['scale' => 1.0],
        ],
        'font_style' => 'inter',
        'layout_variant' => 'standard',
        'sections' => ['header', 'services', 'staff', 'hours', 'contacts'],
    ],

    'barber_dark' => [
        'label' => 'Barber — Dark Premium',
        'vertical' => 'barber',
        'theme' => [
            'colors' => ['primary' => '#0B1220', 'secondary' => '#C8A24B'],
            'radius' => ['small' => 6, 'medium' => 10, 'large' => 20],
            'typography' => ['scale' => 1.0],
        ],
        'font_style' => 'oswald',
        'layout_variant' => 'hero_dark',
        'sections' => ['header', 'services', 'staff', 'hours', 'contacts'],
    ],

    'beauty_visual' => [
        'label' => 'Beauty — Visual',
        'vertical' => 'beauty',
        'theme' => [
            'colors' => ['primary' => '#7C3AED', 'secondary' => '#EC4899'],
            'radius' => ['small' => 12, 'medium' => 18, 'large' => 28],
            'typography' => ['scale' => 1.05],
        ],
        'font_style' => 'poppins',
        'layout_variant' => 'gallery',
        'sections' => ['header', 'gallery', 'services', 'staff', 'contacts'],
    ],

    'medical_clean' => [
        'label' => 'Medical — Clean',
        'vertical' => 'medical',
        'theme' => [
            'colors' => ['primary' => '#0E7490', 'secondary' => '#15803D'],
            'radius' => ['small' => 8, 'medium' => 12, 'large' => 16],
            'typography' => ['scale' => 1.0],
        ],
        'font_style' => 'inter',
        'layout_variant' => 'standard',
        'sections' => ['header', 'services', 'staff', 'hours', 'contacts'],
    ],

    'restaurant_visual' => [
        'label' => 'Restaurant — Visual',
        'vertical' => 'other',
        'theme' => [
            'colors' => ['primary' => '#7F1D1D', 'secondary' => '#B45309'],
            'radius' => ['small' => 10, 'medium' => 16, 'large' => 24],
            'typography' => ['scale' => 1.0],
        ],
        'font_style' => 'poppins',
        // "prenota tavolo" = richiesta di appuntamento (motore invariato).
        'layout_variant' => 'gallery',
        'sections' => ['header', 'gallery', 'menu', 'hours', 'contacts'],
    ],
];
