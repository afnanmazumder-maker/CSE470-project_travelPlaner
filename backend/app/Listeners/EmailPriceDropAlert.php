<?php

namespace App\Listeners;

use App\Events\FlightPriceDropped;
use App\Mail\PriceDropMail;
use Illuminate\Support\Facades\Mail;

/**
 * R9: sends the price-drop alert to the traveler who watches the route.
 */
class EmailPriceDropAlert
{
    public function handle(FlightPriceDropped $event): void
    {
        Mail::to($event->alert->user)->send(new PriceDropMail($event->alert, $event->price, $event->previousPrice));
    }
}
