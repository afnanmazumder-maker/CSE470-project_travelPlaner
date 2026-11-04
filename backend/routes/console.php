<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// R9: re-check watched flight routes for price drops.
\Illuminate\Support\Facades\Schedule::command('price-alerts:check')->hourly()->withoutOverlapping();
