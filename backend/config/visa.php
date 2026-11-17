<?php

/*
| R6: visa and entry requirement checker.
*/

return [
    // Passport preselected on the checker page when the traveler has not saved one.
    'default_passport' => 'BD',

    // Layer 1 data: the Passport Index snapshot shipped in database/data/visa.
    'dataset' => [
        'requirements_csv' => database_path('data/visa/passport-index-tidy-iso2.csv'),
        'countries_csv' => database_path('data/visa/countries.csv'),
        'updated_on' => '2026-02-17',
        'source_url' => 'https://github.com/imorte/passport-index-data',
    ],

    // A row whose last check is older than this is flagged as stale for admins and travelers.
    'stale_after_days' => 365,

    'disclaimer' => 'Visa rules change often. This is guidance only: confirm with the official source or the embassy before you book or travel.',
];
