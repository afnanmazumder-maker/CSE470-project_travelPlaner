<?php

namespace App\Console\Commands;

use App\Services\CurrencyService;
use Illuminate\Console\Command;

/** R8: caches today's Frankfurter (ECB) reference rates. */
class RefreshExchangeRates extends Command
{
    protected $signature = 'rates:refresh';

    protected $description = 'Fetch and cache the latest Frankfurter exchange rates';

    public function handle(CurrencyService $currency): int
    {
        $count = $currency->refreshLatest();

        if ($count === 0) {
            $this->warn('Frankfurter could not be reached; the cached rates stay in use.');

            return self::FAILURE;
        }

        $this->info("Stored {$count} exchange rates.");

        return self::SUCCESS;
    }
}
