<?php

namespace App\Models;

use App\Observers\BookingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([BookingObserver::class])]
class Booking extends Model
{
    /** @var list<string> */
    public const TYPES = ['flight', 'stay', 'activity'];

    /** @var list<string> */
    public const STATUSES = ['planned', 'confirmed', 'cancelled'];

    protected $fillable = [
        'budget_id', 'type', 'flight_id', 'accommodation_id', 'title', 'provider',
        'confirmation_number', 'start_at', 'end_at', 'status', 'price', 'party_size',
        'cancellation_deadline', 'payment_due_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime:Y-m-d\TH:i',
            'end_at' => 'datetime:Y-m-d\TH:i',
            'cancellation_deadline' => 'datetime:Y-m-d\TH:i',
            'payment_due_at' => 'datetime:Y-m-d\TH:i',
            'price' => 'float',
            'party_size' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class);
    }

    public function accommodation(): BelongsTo
    {
        return $this->belongsTo(Accommodation::class);
    }
}
