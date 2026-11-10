<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CulturalTip extends Model
{
    /** @var array<string, string> */
    public const TOPICS = [
        'tipping' => 'Tipping',
        'dress_code' => 'Dress code',
        'customs' => 'Customs and manners',
        'business_hours' => 'Business hours',
    ];

    protected $fillable = ['country', 'language_code', 'topic', 'title', 'body'];
}
