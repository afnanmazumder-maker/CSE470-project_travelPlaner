<?php

namespace App\Listeners;

use App\Events\FlightPriceDropped;
use App\Services\NotificationService;

/**
 * R16: tells the traveler when R9 announces a price drop on a watched route.
 */
class SendPriceDropNotification
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(FlightPriceDropped $event): void
    {
        $alert = $event->alert;
        $route = "{$alert->origin} → {$alert->destination}";
        $when = $alert->depart_date ? ' on '.$alert->depart_date->format('j M Y') : ' in the next 90 days';

        $details = array_filter([
            $event->previousPrice !== null ? 'was $'.number_format($event->previousPrice, 2) : null,
            $alert->target_price !== null ? 'your target is $'.number_format($alert->target_price, 2) : null,
        ]);

        $this->notifications->notify(
            $alert->user,
            'price_drop',
            sprintf('%s fares dropped to $%s', $route, number_format($event->price, 2)),
            sprintf(
                'The cheapest %s fare%s is now $%s per passenger%s.',
                str_replace('_', ' ', $alert->cabin_class), $when, number_format($event->price, 2),
                $details ? ' ('.implode(', ', $details).')' : '',
            ),
            ['price_alert_id' => $alert->id, 'price' => $event->price],
            dedupeKey: "price_drop:{$alert->id}:{$event->price}",
        );
    }
}
