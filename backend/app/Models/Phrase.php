<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Phrase extends Model
{
    /** @var list<string> */
    public const CATEGORIES = ['greetings', 'food', 'directions', 'emergencies'];

    protected $fillable = [
        'language_code', 'language_name', 'category', 'english',
        'translation', 'romanization', 'pronunciation',
    ];

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'phrase_favorites')->withTimestamps();
    }
}
