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
