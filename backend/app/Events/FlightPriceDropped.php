<?php

namespace App\Events;

use App\Models\PriceAlert;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * R9: the cheapest fare on a watched route fell below the last price seen or
 * reached the traveler's target. R16 turns this into a notification.
 */
class FlightPriceDropped
{
    use Dispatchable;

    public function __construct(
        public PriceAlert $alert,
        public float $price,
        public ?float $previousPrice,
    ) {}
}
