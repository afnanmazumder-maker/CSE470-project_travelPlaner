<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * R6: entry rule for holders of one passport travelling to one destination.
 */
class VisaRequirement extends Model
{
    /** Requirement types, from easiest to hardest entry. */
    public const REQUIREMENTS = [
        'visa_free' => 'Visa-free',
        'visa_on_arrival' => 'Visa on arrival',
        'eta' => 'Electronic travel authorisation (ETA)',
        'e_visa' => 'E-visa (apply online before travel)',
        'visa_required' => 'Embassy visa required',
        'no_admission' => 'Entry not permitted',
    ];

    /** Types that need an application or approval before the traveler flies. */
    public const NEEDS_ACTION = ['eta', 'e_visa', 'visa_required', 'no_admission'];

    /** dataset = Passport Index import, official = checked on a government site, admin = edited by an admin. */
    public const SOURCES = ['dataset', 'official', 'admin'];

    protected $fillable = [
        'passport_code', 'destination_code', 'requirement', 'max_stay_days', 'conditions',
        'required_documents', 'passport_validity_months', 'fee_note', 'processing_note',
        'transit_notes', 'official_url', 'source', 'last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'required_documents' => 'array',
            'max_stay_days' => 'integer',
            'passport_validity_months' => 'integer',
            'last_verified_at' => 'date',
        ];
    }

    public function passport(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'passport_code', 'code');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'destination_code', 'code');
    }

    public function label(): string
    {
        return self::REQUIREMENTS[$this->requirement] ?? $this->requirement;
    }

    public function needsActionBeforeTravel(): bool
    {
        return in_array($this->requirement, self::NEEDS_ACTION, true);
    }

    /**
     * True when the row was never checked, or its last check is older than the configured limit.
     */
    public function isStale(): bool
    {
        if ($this->last_verified_at === null) {
            return true;
        }

        return $this->last_verified_at->lt(now()->subDays(config('visa.stale_after_days'))->startOfDay());
    }

    public function isVerified(): bool
    {
        return $this->source !== 'dataset';
    }
}
