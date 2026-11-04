<?php

/*
| R9: airports used by the admin-managed flight data and the cabin classes
| travelers can search. Flight times are local times at each airport.
*/

return [
    'airports' => [
        'DAC' => ['city' => 'Dhaka', 'name' => 'Hazrat Shahjalal International', 'utc_offset' => 6],
        'CDG' => ['city' => 'Paris', 'name' => 'Charles de Gaulle', 'utc_offset' => 1],
        'FCO' => ['city' => 'Rome', 'name' => 'Leonardo da Vinci–Fiumicino', 'utc_offset' => 1],
        'NRT' => ['city' => 'Tokyo', 'name' => 'Narita International', 'utc_offset' => 9],
        'IST' => ['city' => 'Istanbul', 'name' => 'Istanbul Airport', 'utc_offset' => 3],
        'BKK' => ['city' => 'Bangkok', 'name' => 'Suvarnabhumi', 'utc_offset' => 7],
        'DPS' => ['city' => 'Bali', 'name' => 'Ngurah Rai International', 'utc_offset' => 8],
        'DXB' => ['city' => 'Dubai', 'name' => 'Dubai International', 'utc_offset' => 4],
        'SIN' => ['city' => 'Singapore', 'name' => 'Changi', 'utc_offset' => 8],
        'KUL' => ['city' => 'Kuala Lumpur', 'name' => 'Kuala Lumpur International', 'utc_offset' => 8],
    ],

    'cabin_classes' => ['economy', 'premium_economy', 'business', 'first'],
];
