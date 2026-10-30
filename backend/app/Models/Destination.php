<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Destination extends Model
{
    protected $fillable = [
        'name', 'country', 'region', 'overview', 'cuisine', 'budget_level',
        'cost_index', 'climate', 'best_months', 'themes', 'safety_rating',
        'latitude', 'longitude', 'image_url', 'banner_url'
    ];

    protected function casts(): array
    {
        return [
            'best_months' => 'array',
            'themes' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function attractions(): HasMany
    {
        return $this->hasMany(Attraction::class);
    }
}