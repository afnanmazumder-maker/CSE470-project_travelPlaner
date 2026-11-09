<?php

/*
| R8: daily reference rates (ECB) from Frankfurter, cached in exchange_rates.
| Budgets are in USD, so every expense is converted to USD.
*/

return [
    'home' => 'USD',

    'frankfurter' => [
        'url' => env('FRANKFURTER_URL', 'https://api.frankfurter.dev/v1'),
        'timeout' => 5,
    ],

    // Currencies Frankfurter publishes. BDT, AED and others are not in the ECB set.
    'currencies' => [
        'AUD', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD',
        'HUF', 'IDR', 'ILS', 'INR', 'ISK', 'JPY', 'KRW', 'MXN', 'MYR', 'NOK', 'NZD',
        'PHP', 'PLN', 'RON', 'SEK', 'SGD', 'THB', 'TRY', 'USD', 'ZAR',
    ],

    // A category alerts when it is this % over plan.
    'default_alert_threshold_pct' => 10,
];
