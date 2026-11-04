<?php

namespace App\Services;

use App\Models\Flight;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * R9: searches the admin-managed flight data: one-way, multi-city and a
 * flexible-date price calendar.
 */
class FlightSearchService
{
    public const SORTS = ['price', 'duration', 'departure', 'arrival'];

    /**
     * @param  array{origin: string, destination: string, date: string, passengers: int, cabin_class: string,
     *               sort?: string, max_price?: float, max_stops?: int, max_duration_hours?: float, depart_windows?: array}  $criteria
     */
    public function search(array $criteria): Collection
    {
        $date = Carbon::parse($criteria['date']);
        $passengers = (int) $criteria['passengers'];

        $flights = Flight::query()
            ->where('origin', strtoupper($criteria['origin']))
            ->where('destination', strtoupper($criteria['destination']))
            ->where('cabin_class', $criteria['cabin_class'])
            ->whereBetween('depart_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->where('seats_available', '>=', $passengers)
            ->when(isset($criteria['max_price']), fn ($q) => $q->where('price', '<=', $criteria['max_price']))
            ->when(isset($criteria['max_stops']), fn ($q) => $q->where('stops', '<=', $criteria['max_stops']))
            ->when(isset($criteria['max_duration_hours']), fn ($q) => $q->where('duration_minutes', '<=', $criteria['max_duration_hours'] * 60))
            ->get();

        if (! empty($criteria['depart_windows'])) {
            $flights = $flights->whereIn('depart_window', $criteria['depart_windows']);
        }

        return $this->sort($flights, $criteria['sort'] ?? 'price')
            ->map(fn (Flight $flight) => $flight->toArray() + ['total_price' => round($flight->price * $passengers, 2)])
            ->values();
    }

    /**
     * Searches each leg of a multi-city trip and totals the cheapest option.
     *
     * @param  array<int, array{origin: string, destination: string, date: string}>  $legs
     */
    public function multiCity(array $legs, int $passengers, string $cabinClass, string $sort = 'price'): array
    {
        $results = collect($legs)->values()->map(function (array $leg, int $index) use ($passengers, $cabinClass, $sort) {
            $flights = $this->search($leg + ['passengers' => $passengers, 'cabin_class' => $cabinClass, 'sort' => $sort]);

            return [
                'leg' => $index + 1,
                'origin' => strtoupper($leg['origin']),
                'destination' => strtoupper($leg['destination']),
                'date' => $leg['date'],
                'flights' => $flights,
                'cheapest_total' => $flights->min('total_price'),
            ];
        });

        $complete = $results->every(fn (array $leg) => $leg['cheapest_total'] !== null);

        return [
            'legs' => $results,
            'cheapest_trip_total' => $complete ? round($results->sum('cheapest_total'), 2) : null,
        ];
    }

    /**
     * Cheapest fare per day for a month, with low/mid/high bands for colouring.
     */
    public function priceCalendar(string $origin, string $destination, string $cabinClass, Carbon $month, int $passengers = 1): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $byDate = Flight::query()
            ->where('origin', strtoupper($origin))
            ->where('destination', strtoupper($destination))
            ->where('cabin_class', $cabinClass)
            ->where('seats_available', '>=', $passengers)
            ->whereBetween('depart_at', [$start, $end->copy()->endOfDay()])
            ->get(['depart_at', 'price'])
            ->groupBy(fn (Flight $f) => $f->depart_at->toDateString())
            ->map(fn (Collection $day) => ['min_price' => $day->min('price'), 'flights' => $day->count()]);

        $prices = $byDate->pluck('min_price')->sort()->values();
        $low = $prices->isEmpty() ? null : $prices[(int) floor(($prices->count() - 1) / 3)];
        $high = $prices->isEmpty() ? null : $prices[(int) floor(($prices->count() - 1) * 2 / 3)];

        $days = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $entry = $byDate[$day->toDateString()] ?? null;
            $price = $entry['min_price'] ?? null;
            $days[] = [
                'date' => $day->toDateString(),
                'min_price' => $price,
                'flights' => $entry['flights'] ?? 0,
                'band' => $price === null ? null : ($price <= $low ? 'low' : ($price <= $high ? 'mid' : 'high')),
            ];
        }

        $cheapest = collect($days)->whereNotNull('min_price')->sortBy('min_price')->first();

        return [
            'origin' => strtoupper($origin),
            'destination' => strtoupper($destination),
            'cabin_class' => $cabinClass,
            'month' => $start->format('Y-m'),
            'days' => $days,
            'cheapest' => $cheapest,
        ];
    }

    /** Cheapest per-passenger fare on a date, or over the next 90 days. */
    public function cheapestFare(string $origin, string $destination, string $cabinClass, ?Carbon $date = null): ?float
    {
        $from = $date ? $date->copy()->startOfDay() : now();
        $to = $date ? $date->copy()->endOfDay() : now()->addDays(90);

        $price = Flight::query()
            ->where('origin', $origin)
            ->where('destination', $destination)
            ->where('cabin_class', $cabinClass)
            ->where('seats_available', '>', 0)
            ->whereBetween('depart_at', [$from, $to])
            ->min('price');

        return $price === null ? null : (float) $price;
    }

    protected function sort(Collection $flights, string $sort): Collection
    {
        return match ($sort) {
            'duration' => $flights->sortBy([['duration_minutes', 'asc'], ['price', 'asc']]),
            'departure' => $flights->sortBy(fn (Flight $f) => $f->depart_at->timestamp),
            'arrival' => $flights->sortBy(fn (Flight $f) => $f->arrive_at->timestamp),
            default => $flights->sortBy([['price', 'asc'], ['duration_minutes', 'asc']]),
        };
    }
}
