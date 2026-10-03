<?php

use Illuminate\Support\Facades\Schedule;

// Keep imported opportunities fresh. Requires the standard Laravel cron entry:
// * * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
if (config('volunteering.api.schedule_daily')) {
    Schedule::command('opportunities:sync')->dailyAt('03:15')->withoutOverlapping();
}
