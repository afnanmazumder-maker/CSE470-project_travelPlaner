<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * R6: a country identified by its ISO 3166-1 alpha-2 code (BD, TH, FR...).
 */
class Country extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['code', 'name'];

    /**
     * Finds a country by its English name, as stored on destinations ("Thailand").
     */
    public static function findByName(string $name): ?self
    {
        return static::whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])->first();
    }
}
