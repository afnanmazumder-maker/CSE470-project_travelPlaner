<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// R9: re-check watched flight routes for price drops.
\Illuminate\Support\Facades\Schedule::command('price-alerts:check')->hourly()->withoutOverlapping();

// R16: reminders and daily digests; dedupe keys make repeated runs safe.
\Illuminate\Support\Facades\Schedule::command('reminders:dispatch')->everyFifteenMinutes()->withoutOverlapping();

// R8: ECB publishes reference rates around 16:00 CET.
\Illuminate\Support\Facades\Schedule::command('rates:refresh')->dailyAt('15:30')->withoutOverlapping();
