<?php

namespace Database\Seeders;

use App\Models\Flight;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * R9: generates admin-style flight data for every route, every day from
 * November 2026 to June 2027, in each cabin the airline sells.
 * Prices are deterministic (seeded random) so tests and demos repeat.
 */
class FlightSeeder extends Seeder
{
    private const CABIN_MULTIPLIER = ['economy' => 1.0, 'premium_economy' => 1.65, 'business' => 3.1, 'first' => 5.4];

    private const FIRST_CLASS_AIRLINES = ['EK', 'SQ', 'JL', 'NH', 'QR'];

    public function run(): void
    {
        mt_srand(470);
        Flight::query()->delete();

        // [origin, destination, base economy fare USD, [[airline, code, number, local departure, minutes, stops, stopover]]]
        $routes = [
            ['DAC', 'BKK', 210, [['Thai Airways', 'TG', 'TG322', '12:45', 150, 0, null], ['Biman Bangladesh Airlines', 'BG', 'BG388', '10:15', 155, 0, null], ['US-Bangla Airlines', 'BS', 'BS216', '15:20', 160, 0, null]]],
            ['BKK', 'DAC', 205, [['Thai Airways', 'TG', 'TG321', '09:50', 165, 0, null], ['Biman Bangladesh Airlines', 'BG', 'BG389', '14:10', 170, 0, null], ['Thai Airways', 'TG', 'TG339', '19:30', 150, 0, null]]],
            ['DAC', 'NRT', 620, [['Biman Bangladesh Airlines', 'BG', 'BG378', '23:55', 380, 0, null], ['Thai Airways', 'TG', 'TG322', '12:45', 720, 1, 'BKK'], ['Singapore Airlines', 'SQ', 'SQ447', '23:50', 840, 1, 'SIN']]],
            ['NRT', 'DAC', 610, [['Biman Bangladesh Airlines', 'BG', 'BG379', '11:30', 420, 0, null], ['Thai Airways', 'TG', 'TG677', '10:30', 690, 1, 'BKK'], ['Singapore Airlines', 'SQ', 'SQ637', '11:05', 900, 1, 'SIN']]],
            ['NRT', 'BKK', 260, [['Thai Airways', 'TG', 'TG677', '10:30', 390, 0, null], ['Japan Airlines', 'JL', 'JL717', '17:30', 405, 0, null], ['All Nippon Airways', 'NH', 'NH805', '11:00', 400, 0, null]]],
            ['BKK', 'NRT', 270, [['Thai Airways', 'TG', 'TG640', '22:45', 360, 0, null], ['Japan Airlines', 'JL', 'JL708', '07:30', 355, 0, null], ['All Nippon Airways', 'NH', 'NH806', '22:30', 350, 0, null]]],
            ['DAC', 'IST', 480, [['Turkish Airlines', 'TK', 'TK713', '06:55', 555, 0, null], ['Qatar Airways', 'QR', 'QR639', '03:50', 720, 1, 'DOH'], ['Emirates', 'EK', 'EK585', '21:30', 690, 1, 'DXB']]],
            ['IST', 'DAC', 470, [['Turkish Airlines', 'TK', 'TK712', '19:55', 470, 0, null], ['Emirates', 'EK', 'EK124', '09:30', 700, 1, 'DXB']]],
            ['DAC', 'CDG', 560, [['Emirates', 'EK', 'EK583', '10:20', 1010, 1, 'DXB'], ['Qatar Airways', 'QR', 'QR641', '20:05', 960, 1, 'DOH'], ['Turkish Airlines', 'TK', 'TK713', '06:55', 920, 1, 'IST']]],
            ['CDG', 'FCO', 110, [['Air France', 'AF', 'AF1304', '09:40', 125, 0, null], ['ITA Airways', 'AZ', 'AZ319', '13:15', 120, 0, null], ['Air France', 'AF', 'AF1504', '18:20', 125, 0, null]]],
            ['FCO', 'CDG', 115, [['ITA Airways', 'AZ', 'AZ318', '07:50', 125, 0, null], ['Air France', 'AF', 'AF1205', '12:30', 130, 0, null], ['Air France', 'AF', 'AF1405', '20:10', 125, 0, null]]],
            ['FCO', 'DAC', 590, [['Emirates', 'EK', 'EK098', '15:30', 940, 1, 'DXB'], ['Qatar Airways', 'QR', 'QR116', '16:45', 890, 1, 'DOH'], ['Turkish Airlines', 'TK', 'TK1866', '11:10', 985, 1, 'IST']]],
            ['DAC', 'DXB', 290, [['Emirates', 'EK', 'EK583', '10:20', 330, 0, null], ['Biman Bangladesh Airlines', 'BG', 'BG347', '20:40', 340, 0, null], ['US-Bangla Airlines', 'BS', 'BS341', '21:05', 335, 0, null]]],
            ['DXB', 'DAC', 300, [['Emirates', 'EK', 'EK582', '02:50', 300, 0, null], ['Biman Bangladesh Airlines', 'BG', 'BG348', '08:15', 310, 0, null]]],
            ['DAC', 'SIN', 260, [['Singapore Airlines', 'SQ', 'SQ447', '23:50', 265, 0, null], ['US-Bangla Airlines', 'BS', 'BS209', '14:00', 270, 0, null]]],
            ['DAC', 'KUL', 230, [['Malaysia Airlines', 'MH', 'MH197', '23:15', 235, 0, null], ['US-Bangla Airlines', 'BS', 'BS315', '09:30', 240, 0, null]]],
            ['KUL', 'DAC', 225, [['Malaysia Airlines', 'MH', 'MH196', '21:05', 225, 0, null], ['US-Bangla Airlines', 'BS', 'BS316', '13:40', 230, 0, null]]],
            ['DAC', 'DPS', 410, [['Malaysia Airlines', 'MH', 'MH197', '23:15', 520, 1, 'KUL'], ['Singapore Airlines', 'SQ', 'SQ447', '23:50', 560, 1, 'SIN']]],
            ['DPS', 'DAC', 400, [['Malaysia Airlines', 'MH', 'MH850', '12:40', 545, 1, 'KUL'], ['Singapore Airlines', 'SQ', 'SQ943', '17:35', 590, 1, 'SIN']]],
            ['BKK', 'DPS', 190, [['Thai Airways', 'TG', 'TG431', '09:30', 260, 0, null], ['AirAsia', 'FD', 'FD396', '15:10', 265, 0, null]]],
            ['DPS', 'BKK', 185, [['Thai Airways', 'TG', 'TG432', '15:45', 265, 0, null], ['AirAsia', 'FD', 'FD397', '19:20', 270, 0, null]]],
        ];

        $first = Carbon::create(2026, 11, 1);
        $last = Carbon::create(2027, 6, 30);
        $now = now();
        $offsets = array_map(fn (array $airport): int => $airport['utc_offset'], config('flights.airports'));
        $rows = [];

        foreach ($routes as $routeIndex => [$origin, $destination, $base, $services]) {
            for ($day = $first->copy(), $i = 0; $day->lte($last); $day->addDay(), $i++) {
                $dayFactor = 1 + 0.15 * sin($i / 9 + $routeIndex) + $this->seasonFactor($day)
                    + (in_array($day->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY], true) ? 0.08 : 0);

                foreach ($services as [$airline, $code, $number, $time, $minutes, $stops, $stopover]) {
                    $depart = Carbon::parse($day->toDateString().' '.$time);
                    $arrive = $depart->copy()->addMinutes($minutes + 60 * ($offsets[$destination] - $offsets[$origin]));
                    $serviceFactor = ($stops === 0 ? 1.08 : 0.95) * (1 + mt_rand(-7, 9) / 100);

                    foreach ($this->cabinsFor($code, $minutes) as $cabin) {
                        $rows[] = [
                            'airline' => $airline,
                            'airline_code' => $code,
                            'flight_number' => $number,
                            'origin' => $origin,
                            'destination' => $destination,
                            'depart_at' => $depart,
                            'arrive_at' => $arrive,
                            'duration_minutes' => $minutes,
                            'stops' => $stops,
                            'stopover' => $stopover,
                            'cabin_class' => $cabin,
                            'price' => round($base * $dayFactor * $serviceFactor * self::CABIN_MULTIPLIER[$cabin]),
                            'seats_available' => mt_rand(0, 12) === 0 ? 0 : mt_rand(1, 9),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        if (count($rows) === 1000) {
                            Flight::insert($rows);
                            $rows = [];
                        }
                    }
                }
            }
        }

        if ($rows) {
            Flight::insert($rows);
        }
    }

    private function cabinsFor(string $airline, int $minutes): array
    {
        $cabins = ['economy', 'business'];

        if ($minutes > 300) {
            $cabins[] = 'premium_economy';
            if (in_array($airline, self::FIRST_CLASS_AIRLINES, true)) {
                $cabins[] = 'first';
            }
        }

        return $cabins;
    }

    /** Year-end holidays and spring are pricier. */
    private function seasonFactor(Carbon $day): float
    {
        return match (true) {
            ($day->month === 12 && $day->day >= 15) || ($day->month === 1 && $day->day <= 5) => 0.25,
            $day->month === 4 => 0.1,
            default => 0.0,
        };
    }
}
