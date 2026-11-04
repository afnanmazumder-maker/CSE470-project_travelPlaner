<?php

namespace App\Console\Commands;

use App\Services\PriceAlertService;
use Illuminate\Console\Command;

/** R9: announces price drops on watched routes. */
class CheckPriceAlerts extends Command
{
    protected $signature = 'price-alerts:check';

    protected $description = 'Check every active price alert for a price drop';

    public function handle(PriceAlertService $alerts): int
    {
        $this->info("Announced {$alerts->check()->count()} price drops.");

        return self::SUCCESS;
    }
}
