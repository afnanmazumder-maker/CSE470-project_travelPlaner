<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceAlert extends Model
{
    protected $fillable = [
        'origin', 'destination', 'depart_date', 'cabin_class', 'target_price',
        'last_seen_price', 'last_notified_price', 'last_checked_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'depart_date' => 'date:Y-m-d',
            'target_price' => 'float',
            'last_seen_price' => 'float',
            'last_notified_price' => 'float',
            'last_checked_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
