<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\Destination;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * R9: filters stays by price, rating, distance, amenities and property type,
 * and supports map search by the visible map bounds.
 */
class StaySearchService
{
    /** @var list<string> */
    public const SORTS = ['price', 'rating', 'distance', 'reviews'];

    /**
     * @param  array<string, mixed>  $criteria
     * @return array{center: array{lat: float, lng: float, label: string}|null, nights: int|null, count: int, results: Collection<int, array<string, mixed>>}
     */
    public function search(array $criteria): array
    {
        $destination = ! empty($criteria['destination_id']) ? Destination::find($criteria['destination_id']) : null;
        $hasBounds = isset($criteria['north'], $criteria['south'], $criteria['east'], $criteria['west']);

        $stays = Accommodation::query()
            ->with('destination:id,name,country')
            ->when($destination, fn ($query) => $query->where('destination_id', $destination->id))
            ->when($hasBounds, fn ($query) => $query
                ->whereBetween('latitude', [$criteria['south'], $criteria['north']])
                ->whereBetween('longitude', [$criteria['west'], $criteria['east']]))
            ->when(isset($criteria['min_price']), fn ($query) => $query->where('price_per_night', '>=', $criteria['min_price']))
            ->when(isset($criteria['max_price']), fn ($query) => $query->where('price_per_night', '<=', $criteria['max_price']))
            ->when(isset($criteria['min_rating']), fn ($query) => $query->where('rating', '>=', $criteria['min_rating']))
            ->when(! empty($criteria['property_types']), fn ($query) => $query->whereIn('property_type', $criteria['property_types']))
            ->get();

        if (! empty($criteria['amenities'])) {
            $stays = $stays->filter(fn (Accommodation $stay) => empty(array_diff($criteria['amenities'], $stay->amenities)));
        }

        $center = match (true) {
            $destination !== null => ['lat' => $destination->latitude, 'lng' => $destination->longitude, 'label' => "{$destination->name} centre"],
            $hasBounds => ['lat' => ($criteria['north'] + $criteria['south']) / 2, 'lng' => ($criteria['east'] + $criteria['west']) / 2, 'label' => 'Map centre'],
            $stays->isNotEmpty() => ['lat' => $stays->avg('latitude'), 'lng' => $stays->avg('longitude'), 'label' => 'Results centre'],
            default => null,
        };
        $nights = $this->nights($criteria);

        $results = $stays->map(function (Accommodation $stay) use ($center, $nights): array {
            $distance = $center ? self::distanceKm($center['lat'], $center['lng'], $stay->latitude, $stay->longitude) : null;

            return $stay->toArray() + [
                'distance_km' => $distance === null ? null : round($distance, 2),
                'nights' => $nights,
                'total_price' => $nights ? round($stay->price_per_night * $nights, 2) : null,
            ];
        });

        if (isset($criteria['max_distance_km']) && $center) {
            $results = $results->where('distance_km', '<=', $criteria['max_distance_km']);
        }

        $results = match ($criteria['sort'] ?? 'price') {
            'rating' => $results->sortByDesc('rating'),
            'distance' => $results->sortBy('distance_km'),
            'reviews' => $results->sortByDesc('review_count'),
            default => $results->sortBy('price_per_night'),
        };

        return ['center' => $center, 'nights' => $nights, 'count' => $results->count(), 'results' => $results->values()];
    }

    /** Great-circle distance in kilometres (haversine). */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371.0 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    private function nights(array $criteria): ?int
    {
        if (empty($criteria['check_in']) || empty($criteria['check_out'])) {
            return null;
        }

        return (int) Carbon::parse($criteria['check_in'])->diffInDays(Carbon::parse($criteria['check_out']));
    }
}
