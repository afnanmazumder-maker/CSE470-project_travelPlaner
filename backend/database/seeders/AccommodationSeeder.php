<?php

namespace Database\Seeders;

use App\Models\Accommodation;
use App\Models\Destination;
use Illuminate\Database\Seeder;

/**
 * R9: fictional stay listings scattered around each destination centre.
 * Run after DestinationSeeder (it is called here if destinations are missing).
 */
class AccommodationSeeder extends Seeder
{
    public function run(): void
    {
        if (Destination::count() === 0) {
            $this->call(DestinationSeeder::class);
        }

        mt_srand(4709);
        Accommodation::query()->delete();

        // destination => [[name, type, price/night USD, area]]
        $listings = [
            'Tokyo' => [
                ['Shinjuku Garden Hotel', 'hotel', 165, 'Shinjuku'], ['Asakusa Lantern Hostel', 'hostel', 38, 'Asakusa'],
                ['Shibuya Loft Apartment', 'apartment', 140, 'Shibuya'], ['Ginza Grand Residence', 'hotel', 290, 'Ginza'],
                ['Ueno Park Guesthouse', 'guesthouse', 72, 'Ueno'], ['Ikebukuro Capsule Stay', 'hostel', 29, 'Ikebukuro'],
                ['Odaiba Bay Resort', 'resort', 240, 'Odaiba'], ['Akihabara Pixel Inn', 'hotel', 98, 'Akihabara'],
            ],
            'Bangkok' => [
                ['Riverside Bangkok Residence', 'apartment', 85, 'Riverside'], ['Sukhumvit Sky Hotel', 'hotel', 110, 'Sukhumvit'],
                ['Khao San Backpackers', 'hostel', 14, 'Banglamphu'], ['Silom Orchid Hotel', 'hotel', 74, 'Silom'],
                ['Ari Garden Guesthouse', 'guesthouse', 40, 'Ari'], ['Chao Phraya Grand Resort', 'resort', 210, 'Riverside'],
                ['Siam Central Suites', 'hotel', 128, 'Siam'], ['Old Town Teak House', 'guesthouse', 55, 'Rattanakosin'],
            ],
            'Istanbul' => [
                ['Sultanahmet Courtyard Hotel', 'hotel', 120, 'Sultanahmet'], ['Galata View Apartment', 'apartment', 95, 'Beyoğlu'],
                ['Bosphorus Pearl Resort', 'resort', 260, 'Beşiktaş'], ['Kadıköy Corner Hostel', 'hostel', 24, 'Kadıköy'],
                ['Balat Colours Guesthouse', 'guesthouse', 58, 'Balat'], ['Taksim Square Suites', 'hotel', 105, 'Taksim'],
            ],
            'Paris' => [
                ['Le Marais Atelier Hotel', 'hotel', 210, 'Le Marais'], ['Montmartre Studio', 'apartment', 135, 'Montmartre'],
                ['Latin Quarter Hostel', 'hostel', 45, 'Latin Quarter'], ['Saint-Germain Maison', 'hotel', 320, 'Saint-Germain'],
                ['Canal Saint-Martin Flat', 'apartment', 150, 'Canal Saint-Martin'], ['Bastille Petit Hôtel', 'hotel', 165, 'Bastille'],
            ],
            'Rome' => [
                ['Trastevere Terrace Rooms', 'guesthouse', 98, 'Trastevere'], ['Monti Boutique Hotel', 'hotel', 175, 'Monti'],
                ['Termini Easy Hostel', 'hostel', 35, 'Esquilino'], ['Piazza Navona Apartment', 'apartment', 190, 'Centro Storico'],
                ['Vatican Gate Hotel', 'hotel', 145, 'Prati'], ['Aventine Garden Villa', 'resort', 280, 'Aventino'],
            ],
            'Bali' => [
                ['Ubud Rice Terrace Villa', 'resort', 180, 'Ubud'], ['Seminyak Beach House', 'apartment', 120, 'Seminyak'],
                ['Canggu Surf Hostel', 'hostel', 18, 'Canggu'], ['Sanur Garden Guesthouse', 'guesthouse', 45, 'Sanur'],
                ['Nusa Dua Lagoon Resort', 'resort', 260, 'Nusa Dua'], ['Kuta Central Hotel', 'hotel', 70, 'Kuta'],
            ],
        ];

        $amenityPool = Accommodation::AMENITIES;

        foreach ($listings as $city => $stays) {
            $centre = Destination::where('name', $city)->firstOrFail();

            foreach ($stays as [$name, $type, $price, $area]) {
                // Random point within ~6 km of the centre (1° latitude ≈ 111 km).
                $distanceKm = mt_rand(3, 60) / 10;
                $angle = deg2rad(mt_rand(0, 359));
                $lat = $centre->latitude + ($distanceKm / 111) * sin($angle);
                $lng = $centre->longitude + ($distanceKm / (111 * cos(deg2rad($centre->latitude)))) * cos($angle);

                $amenities = ['wifi'];
                foreach ($amenityPool as $amenity) {
                    if ($amenity !== 'wifi' && mt_rand(1, 100) <= ($type === 'resort' ? 75 : ($type === 'hostel' ? 25 : 45))) {
                        $amenities[] = $amenity;
                    }
                }

                Accommodation::create([
                    'destination_id' => $centre->id,
                    'name' => $name,
                    'property_type' => $type,
                    'price_per_night' => $price,
                    'rating' => min(5, max(3.2, round(3.6 + $price / 400 + mt_rand(-4, 6) / 10, 1))),
                    'review_count' => mt_rand(40, 2400),
                    'latitude' => round($lat, 6),
                    'longitude' => round($lng, 6),
                    'address' => "{$area}, {$city}",
                    'amenities' => $amenities,
                    'description' => ucfirst($type)." in {$area}, about {$distanceKm} km from the centre of {$city}.",
                ]);
            }
        }
    }
}
