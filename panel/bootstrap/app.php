<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    // First scheduled task in this app (CLAUDE.md Section 16.8) - requires
    // the one-time `* * * * * php artisan schedule:run` crontab entry
    // (installer/install-ubuntu22.04.sh adds it on new installs; see
    // Section 20 for the already-live-node manual step).
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('zonclave:backup')->dailyAt('03:00');
    })
    ->create();
