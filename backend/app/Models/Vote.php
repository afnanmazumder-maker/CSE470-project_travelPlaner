<?php

namespace App\Models;

use Database\Factories\VoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * R16: one member's vote on an itinerary item: 1 for, -1 against.
 */
class Vote extends Model
{
    /** @use HasFactory<VoteFactory> */
    use HasFactory;

    protected $fillable = ['value'];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
