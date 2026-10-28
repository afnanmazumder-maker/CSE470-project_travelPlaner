<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attraction extends Model
{
    protected $fillable = [
        'destination_id', 'name', 'description', 'tags',
        'popularity', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'popularity' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }
}