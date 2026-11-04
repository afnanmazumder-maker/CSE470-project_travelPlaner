<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flight extends Model
{
    /** Departure-time filter buckets as [first hour, last hour + 1]. */
    public const DEPART_WINDOWS = [
        'night' => [0, 5],
        'morning' => [5, 12],
        'afternoon' => [12, 17],
        'evening' => [17, 24],
    ];

    protected $fillable = [
        'airline', 'airline_code', 'flight_number', 'origin', 'destination',
        'depart_at', 'arrive_at', 'duration_minutes', 'stops', 'stopover',
        'cabin_class', 'price', 'seats_available',
    ];

    protected $appends = ['depart_window'];

    protected function casts(): array
    {
        return [
            // Local wall-clock times at each airport, sent to the frontend without a time zone.
            'depart_at' => 'datetime:Y-m-d\TH:i',
            'arrive_at' => 'datetime:Y-m-d\TH:i',
            'price' => 'float',
            'duration_minutes' => 'integer',
            'stops' => 'integer',
            'seats_available' => 'integer',
        ];
    }

    public function getDepartWindowAttribute(): string
    {
        $hour = $this->depart_at->hour;

        foreach (self::DEPART_WINDOWS as $window => [$from, $to]) {
            if ($hour >= $from && $hour < $to) {
                return $window;
            }
        }

        return 'night';
    }
}
