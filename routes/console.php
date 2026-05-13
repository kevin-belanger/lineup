<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('requests:auto-cancel')->everyMinute();
Schedule::call(function (): void {
    DB::table(config('session.table', 'sessions'))
        ->where('last_activity', '<', now()->subMinutes((int) config('session.lifetime'))->getTimestamp())
        ->delete();
})->daily()->when(fn (): bool => config('session.driver') === 'database')->name('sessions:prune-expired');
Schedule::command('queue:prune-failed --hours=168')->daily()->when(
    fn (): bool => config('queue.default') === 'database'
        && str_starts_with((string) config('queue.failed.driver'), 'database'),
);
