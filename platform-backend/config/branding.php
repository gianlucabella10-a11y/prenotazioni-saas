<?php

declare(strict_types=1);

/**
 * White label defaults (docs/27): every new tenant starts from this curated
 * theme token set, replaced during the Brand Studio step of onboarding.
 * Tokens mirror the Flutter design system contract.
 */
return [
    'default_theme' => [
        // Modalità tema consegnata al client: light | dark | system. Il valore
        // guida `themeMode` in Flutter; la palette `dark` sotto è usata quando
        // il tenant abilita dark/system (deriva automatica per palette custom
        // via DeriveDarkPalette).
        'mode' => 'light',
        'colors' => [
            'primary' => '#1F2937',
            'on_primary' => '#FFFFFF',
            'secondary' => '#C8A24B',
            'on_secondary' => '#1F2937',
            // Accent: elemento di richiamo (ColorScheme.tertiary in Flutter).
            'accent' => '#C8A24B',
            'on_accent' => '#1F2937',
            'surface' => '#FFFFFF',
            'on_surface' => '#111827',
            'background' => '#F9FAFB',
            'success' => '#15803D',
            'warning' => '#B45309',
            'error' => '#B91C1C',
        ],
        // Palette dark curata della piattaforma (default). Per i tenant con
        // palette light personalizzata, DeriveDarkPalette ne calcola una
        // leggibile (contrasto garantito) quando abilitano il dark.
        'dark' => [
            'colors' => [
                'primary' => '#E5B84B',
                'on_primary' => '#1F2937',
                'secondary' => '#C8A24B',
                'on_secondary' => '#1F2937',
                'accent' => '#E5B84B',
                'on_accent' => '#1F2937',
                'surface' => '#111827',
                'on_surface' => '#E5E7EB',
                'background' => '#0B1220',
                'success' => '#4ADE80',
                'warning' => '#FBBF24',
                'error' => '#F87171',
            ],
        ],
        'radius' => [
            'small' => 8,
            'medium' => 12,
            'large' => 24,
        ],
        // Livello ombra dei componenti (card/app bar). 0 = flat, 4 = pronunciato.
        'elevation' => [
            'level' => 1,
        ],
        // App Identity (Fase 2): densità visiva dell'intera app (spaziature dei
        // componenti). comfortable | standard | compact → ThemeData.visualDensity.
        'density' => 'standard',
        'typography' => [
            'scale' => 1.0,
        ],
    ],

    // Customer Experience (Fase 5): copy/immagini editoriali dell'app. I
    // default riproducono le stringhe attuali (nessuna regressione); il tenant
    // sovrascrive solo ciò che vuole rendere "suo". null = voce nascosta.
    'default_content' => [
        'welcome_message' => null,
        'home_title' => 'Il tuo prossimo appuntamento',
        'home_subtitle' => null,
        'primary_cta_label' => 'Prenota ora',
        'empty_appointments' => 'Nessun appuntamento in programma.',
        'hero_image_url' => null,
    ],

    // Push Notifications (Fase 7): stile della notifica. `color` null → usa il
    // primary del brand; `priority` high|normal (heads-up o silenziosa).
    'default_notification' => [
        'color' => null,
        'priority' => 'high',
    ],

    // WCAG AA threshold for normal text (docs/05 RF-12).
    'minimum_contrast_ratio' => 4.5,

    // Disco di storage per gli asset di brand (logo, ecc.): 'public' in
    // sviluppo, 's3' in produzione (override con BRANDING_ASSET_DISK).
    'asset_disk' => env('BRANDING_ASSET_DISK', 'public'),
];
