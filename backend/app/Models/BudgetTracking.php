<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class BudgetTracking extends Model
{
    protected $fillable = ['budget_id', 'start_date', 'alert_threshold_pct', 'alerted_categories'];

    protected $attributes = [
        'alert_threshold_pct' => 10,
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'alert_threshold_pct' => 'integer',
            'alerted_categories' => 'array',
        ];
    }

    public static function forBudget(Budget $budget): self
    {
        return self::firstOrCreate(['budget_id' => $budget->id], ['alert_threshold_pct' => config('currency.default_alert_threshold_pct')]);
    }

    /** Trip day for a date: 0 before the trip, days + 1 after it, null without a start date. */
    public function dayNumberFor(Carbon $date, int $days): ?int
    {
        if (! $this->start_date) {
            return null;
        }

        if ($date->lt($this->start_date)) {
            return 0;
        }

        return min((int) $this->start_date->diffInDays($date) + 1, $days + 1);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}
