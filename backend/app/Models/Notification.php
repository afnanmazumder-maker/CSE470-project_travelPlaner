<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    /** @var array<string, array{label: string, in_app: bool, email: bool}> */
    public const TYPES = [
        'checkin_open' => ['label' => 'Online check-in opening', 'in_app' => true, 'email' => true],
        'flight_24h' => ['label' => 'Flight within 24 hours', 'in_app' => true, 'email' => true],
        'payment_due' => ['label' => 'Payment due', 'in_app' => true, 'email' => true],
        'cancellation_deadline' => ['label' => 'Free-cancellation deadline', 'in_app' => true, 'email' => true],
        'daily_digest' => ['label' => 'Daily digest on travel days', 'in_app' => true, 'email' => true],
        'price_drop' => ['label' => 'Flight price drops', 'in_app' => true, 'email' => true],
        'budget_alert' => ['label' => 'Spending over plan', 'in_app' => true, 'email' => false],
    ];

    protected $table = 'travel_notifications';

    protected $fillable = [
        'budget_id', 'type', 'title', 'body', 'data', 'show_in_app', 'dedupe_key', 'emailed_at', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'show_in_app' => 'boolean',
            'emailed_at' => 'datetime',
            'read_at' => 'datetime',
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
}
