<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:process-queued-email-command')->everyMinute()->withoutOverlapping()->runInBackground();
Schedule::command('auth:clear-resets')->everySixHours()->withoutOverlapping()->runInBackground();
Schedule::command('dms:archive-expired-documents')->dailyAt('02:00')->withoutOverlapping()->runInBackground();
Schedule::command('sanctum:prune-expired --hours=24')->daily()->withoutOverlapping()->runInBackground();
