<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'budget_id', 'category', 'description', 'amount', 'spent_on',
        'currency', 'original_amount', 'rate_used', 'rate_date', 'rate_source',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'spent_on' => 'date:Y-m-d',
            'original_amount' => 'float',
            'rate_used' => 'float',
            'rate_date' => 'date:Y-m-d',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}
