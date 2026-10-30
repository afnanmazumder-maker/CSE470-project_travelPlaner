<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterestWeight extends Model
{
    protected $fillable = ['user_id', 'tag', 'weight'];

    protected function casts(): array
    {
        return ['weight' => 'float'];
    }
}