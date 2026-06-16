<?php

declare(strict_types=1);

/**
 * App Factory: convenzioni e storage per la generazione delle app per-tenant.
 */
return [
    // Reverse-domain della piattaforma per bundle_id/package_name:
    // com.<prefix-senza-com>.t<shortcode> → es. com.platform.t1a2b.
    'bundle_prefix' => env('APP_FACTORY_BUNDLE_PREFIX', 'com.platform'),

    // Disco dove salvare i manifest di build (privato, non pubblico).
    'manifest_disk' => env('APP_FACTORY_MANIFEST_DISK', 'local'),

    // API base url che la build dell'app userà (--dart-define). In FASE 1 è
    // un segnaposto nel manifest; in FASE 2 lo imposta la pipeline di build.
    'api_base_url' => env('APP_FACTORY_API_BASE_URL', 'https://api.example.com/api/v1'),

    // Versione del "core" della master app (FASE 3 — release train). Quando
    // sale, le app dei tenant con `built_core_version` inferiore diventano
    // *stale* e vengono ricostruite a lotti dalla CI matrix.
    'core_version' => env('APP_FACTORY_CORE_VERSION', '1.0.0'),

    // Pacchetto self-contained per-tenant (manifest.json + assets/ + config/),
    // assemblato da ExportAppPackage e scaricabile dalla Control Room. Disco
    // privato (default = manifest_disk) sotto la cartella `export_base`.
    'export_disk' => env('APP_FACTORY_EXPORT_DISK', env('APP_FACTORY_MANIFEST_DISK', 'local')),
    'export_base' => 'generated_apps',
];
