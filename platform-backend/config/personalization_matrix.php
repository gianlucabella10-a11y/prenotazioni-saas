<?php

declare(strict_types=1);

/**
 * Smart Build Matrix (Fase 9). Sorgente unica di verità su COSA si aggiorna a
 * runtime (prossima apertura app, nessuna build) e COSA richiede una nuova
 * build/distribuzione. Letta da BuildImpactMatrix e mostrata al tenant nella
 * pagina Personalizzazione, così è sempre chiaro l'impatto di ogni modifica.
 *
 * runtime = viaggia in GET /app/config (config_version/ETag) → zero build.
 * build   = tocca binario/asset nativi/identità store → serve ricompilare.
 */
return [
    'runtime' => [
        'app_name' => 'Nome app mostrato in-app',
        'tagline' => 'Slogan',
        'colors' => 'Colori (primary, secondary, accent, background, success, warning, error)',
        'theme_mode' => 'Tema chiaro / scuro / automatico',
        'style' => 'Stile forme e livello ombra',
        'density' => 'Densità visiva dell\'app',
        'content' => 'Testi editoriali (benvenuto, titolo home, CTA, empty state)',
        'hero_image' => 'Immagine hero (URL)',
        'contacts_social' => 'Contatti e social (telefono, email, IG, FB, TikTok, WhatsApp)',
        'legal' => 'Link legali (privacy, termini, cookie, assistenza)',
        'vat_number' => 'Partita IVA',
        'coordinates' => 'Coordinate GPS della sede',
        'booking_policy' => 'Regole prenotazione (finestra, slot, preavviso, cancellazione, max cliente)',
        'confirmation_reminder' => 'Conferma automatica e reminder',
        'notification_style' => 'Stile notifiche push (colore, priorità)',
        'logo_in_app' => 'Logo mostrato dentro l\'app',
    ],

    'build' => [
        'app_icon' => 'Icona nativa dell\'app (launcher iOS/Android)',
        'splash_native' => 'Splash screen nativa',
        'favicon' => 'Favicon (build web/PWA)',
        'font_family' => 'Font personalizzato (.ttf da impacchettare)',
        'bundle_id' => 'Bundle ID / package name (identità store, immutabile)',
        'store_name' => 'Nome dell\'app negli store',
        'api_base_url' => 'Endpoint API (dart-define di build)',
        'template_native' => 'Cambio template che introduce asset/layout nativi',
        'core_version' => 'Aggiornamento del core dell\'app (release train)',
    ],
];
