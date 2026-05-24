<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refresh documentation crawlers on their per-crawler cadence. The command decides which are due,
// so an hourly tick is enough for daily/weekly/monthly schedules. Requires the scheduler to run
// (`php artisan schedule:run` via cron in prod; a self-healing loop in scripts/dev-local.sh locally).
Schedule::command('crawlers:run-scheduled')->hourly()->withoutOverlapping();
