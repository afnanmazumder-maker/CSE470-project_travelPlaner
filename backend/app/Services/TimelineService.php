<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * R16: one chronological timeline of flights, stays and booking deadlines,
 * grouped by date. Activities join it when the itinerary feature (R5) stores days.
 */
class TimelineService
{
    /**
     * @param  Collection<int, Booking>  $bookings
     * @return list<array{date: string, is_today: bool, events: Collection<int, array<string, mixed>>}>
     */
    public function group(Collection $bookings): array
    {
        $today = Carbon::today()->toDateString();

        return $this->events($bookings)
            ->groupBy(fn (array $event): string => substr($event['starts_at'], 0, 10))
            ->sortKeys()
            ->map(fn (Collection $events, string $date): array => [
                'date' => $date,
                'is_today' => $date === $today,
                'events' => $events->sortBy('starts_at')->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * Events on one date, used by the daily digest.
     *
     * @param  Collection<int, Booking>  $bookings
     * @return Collection<int, array<string, mixed>>
     */
    public function onDate(Collection $bookings, Carbon $date): Collection
    {
        return $this->events($bookings)
            ->filter(fn (array $event): bool => str_starts_with($event['starts_at'], $date->toDateString()))
            ->sortBy('starts_at')
            ->values();
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     * @return Collection<int, array<string, mixed>>
     */
    private function events(Collection $bookings): Collection
    {
        return $bookings
            ->where('status', '!=', 'cancelled')
            ->flatMap(fn (Booking $booking): array => $this->bookingEvents($booking))
            ->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bookingEvents(Booking $booking): array
    {
        $at = fn (?Carbon $time): ?string => $time?->format('Y-m-d\TH:i');
        $subtitle = collect([$booking->provider, $booking->confirmation_number ? "Conf. {$booking->confirmation_number}" : null])->filter()->implode(' · ');
        $base = ['booking_id' => $booking->id, 'status' => $booking->status];

        $events = $booking->type === 'flight'
            ? [$base + ['kind' => 'flight', 'title' => $booking->title, 'subtitle' => $subtitle, 'starts_at' => $at($booking->start_at), 'ends_at' => $at($booking->end_at)]]
            : array_values(array_filter([
                $base + ['kind' => 'stay', 'title' => "Check in: {$booking->title}", 'subtitle' => $subtitle, 'starts_at' => $at($booking->start_at), 'ends_at' => null],
                $booking->end_at ? $base + ['kind' => 'stay', 'title' => "Check out: {$booking->title}", 'subtitle' => $booking->provider, 'starts_at' => $at($booking->end_at), 'ends_at' => null] : null,
            ]));

        if ($booking->cancellation_deadline) {
            $events[] = $base + ['kind' => 'deadline', 'title' => "Free cancellation ends: {$booking->title}", 'subtitle' => null, 'starts_at' => $at($booking->cancellation_deadline), 'ends_at' => null];
        }

        if ($booking->payment_due_at) {
            $events[] = $base + ['kind' => 'deadline', 'title' => "Payment due: {$booking->title}", 'subtitle' => $booking->price ? '$'.number_format($booking->price, 2) : null, 'starts_at' => $at($booking->payment_due_at), 'ends_at' => null];
        }

        return $events;
    }
}
