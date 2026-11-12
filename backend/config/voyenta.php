<?php

return [
    'tags' => [
        'museum', 'history', 'architecture', 'beach', 'nature',
        'adventure', 'food', 'shopping', 'nightlife', 'art',
    ],
    'paces' => ['relaxed', 'moderate', 'packed'],
    'budget_bands' => ['budget', 'moderate', 'luxury'],

    // R16: where invite links in emails point (the React app).
    'frontend_url' => env('FRONTEND_URL', 'http://127.0.0.1:5173'),
];
