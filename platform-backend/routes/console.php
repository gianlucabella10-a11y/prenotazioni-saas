<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks (docs/29, docs/32)
|--------------------------------------------------------------------------
| The scheduler runs as a single task (singleton service in ECS);
| onOneServer guards against accidental double scheduling.
*/

Schedule::command('notifications:dispatch-due')
    ->everyFifteenMinutes()
    ->onOneServer()
    ->withoutOverlapping();

// Backup automatico giornaliero (AUTOMATION_CATALOG.md #1 — la scelta di
// automazione a più alto rapporto beneficio/costo identificata nella linea
// di audit precedente). Retention integrata in CreatePlatformBackup evita
// che il disco si riempia di backup indefinitamente.
Schedule::command('platform:backup')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping();
