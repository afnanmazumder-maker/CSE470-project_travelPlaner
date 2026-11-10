<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Accommodation extends Model
{
    /** @var list<string> */
    public const PROPERTY_TYPES = ['hotel', 'hostel', 'apartment', 'guesthouse', 'resort'];

    /** @var list<string> */
    public const AMENITIES = ['wifi', 'breakfast', 'pool', 'gym', 'parking', 'air_conditioning', 'kitchen', 'airport_shuttle', 'spa', 'laundry'];

    protected $fillable = [
        'destination_id', 'name', 'property_type', 'price_per_night', 'rating', 'review_count',
        'latitude', 'longitude', 'address', 'amenities', 'description',
    ];

    protected function casts(): array
    {
        return [
            'price_per_night' => 'float',
            'rating' => 'float',
            'review_count' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'amenities' => 'array',
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }
}
