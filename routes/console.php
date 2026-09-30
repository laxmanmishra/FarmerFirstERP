<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduler
|--------------------------------------------------------------------------
| Run `php artisan schedule:work` locally, or a cron entry calling
| `php artisan schedule:run` every minute in production.
*/

Schedule::command('crm:refresh-temperatures')->dailyAt('00:05')->withoutOverlapping()->onOneServer();
Schedule::command('crm:follow-up-reminders')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('crm:release-stale-claims')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
