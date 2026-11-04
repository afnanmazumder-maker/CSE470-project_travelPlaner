<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = ['base', 'quote', 'rate', 'rate_date', 'source'];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'rate_date' => 'date:Y-m-d',
        ];
    }
}
