<?php

namespace App\Services;

use App\Events\FlightPriceDropped;
use App\Models\PriceAlert;
use Illuminate\Support\Collection;

/**
 * R9: checks watched routes and announces a FlightPriceDropped event when the
 * cheapest fare falls below the last price seen or reaches the target.
 */
class PriceAlertService
{
    public function __construct(private FlightSearchService $flights) {}

    /**
     * @param  Collection<int, PriceAlert>|null  $alerts  defaults to every active alert
     * @return Collection<int, string> a message for each drop that was announced
     */
    public function check(?Collection $alerts = null): Collection
    {
        $alerts ??= PriceAlert::where('is_active', true)->get();
        $announced = collect();

        foreach ($alerts as $alert) {
            $price = $this->flights->cheapestFare($alert->origin, $alert->destination, $alert->cabin_class, $alert->depart_date);

            if ($price === null) {
                $alert->update(['last_checked_at' => now()]);

                continue;
            }

            $dropped = $alert->last_seen_price !== null && $price < $alert->last_seen_price;
            $hitTarget = $alert->target_price !== null && $price <= $alert->target_price;
            $alreadyAnnounced = $alert->last_notified_price !== null && $price >= $alert->last_notified_price;

            if (($dropped || $hitTarget) && ! $alreadyAnnounced) {
                FlightPriceDropped::dispatch($alert, $price, $alert->last_seen_price);
                $announced->push(sprintf('%s → %s fares dropped to $%s', $alert->origin, $alert->destination, number_format($price, 2)));
                $alert->last_notified_price = $price;
            }

            $alert->fill(['last_seen_price' => $price, 'last_checked_at' => now()])->save();
        }

        return $announced;
    }

    /**
     * Re-checks only the alerts on one route, after an admin edits a fare.
     *
     * @return Collection<int, string>
     */
    public function checkRoute(string $origin, string $destination, string $cabinClass): Collection
    {
        return $this->check(PriceAlert::where('is_active', true)
            ->where('origin', $origin)
            ->where('destination', $destination)
            ->where('cabin_class', $cabinClass)
            ->get());
    }
}
