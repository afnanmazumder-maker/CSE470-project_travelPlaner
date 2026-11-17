<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Country;
use App\Models\User;
use App\Models\VisaRequirement;

/**
 * R6: answers "what do I need to enter this country with this passport?"
 * Controllers call this service; it reads the VisaRequirement and Country models.
 */
class VisaService
{
    /**
     * The visa result for one passport and destination, plus an optional transit stop.
     *
     * @return array<string, mixed>
     */
    public function check(string $passportCode, string $destinationCode, ?string $transitCode = null): array
    {
        $passport = Country::findOrFail($passportCode);
        $destination = Country::findOrFail($destinationCode);

        $result = [
            'passport' => $this->country($passport),
            'destination' => $this->country($destination),
            'disclaimer' => config('visa.disclaimer'),
            'transit' => $transitCode ? $this->transit($passport, Country::findOrFail($transitCode)) : null,
        ];

        if ($passport->code === $destination->code) {
            return $result + [
                'found' => true,
                'own_country' => true,
                'requirement' => null,
                'summary' => "{$passport->name} is your own country, so you do not need a visa.",
            ];
        }

        $rule = $this->find($passport->code, $destination->code);

        if ($rule === null) {
            return $result + [
                'found' => false,
                'own_country' => false,
                'requirement' => null,
                'summary' => "We have no entry information for {$passport->name} passport holders visiting {$destination->name} yet. Check with the embassy of {$destination->name}.",
            ];
        }

        return $result + ['found' => true, 'own_country' => false] + $this->format($rule);
    }

    /**
     * The visa warning for a trip. Teammates use an R7 budget as the trip, so the destination
     * comes from the budget and the passport from the traveler's saved nationality.
     *
     * @return array<string, mixed>
     */
    public function forBudget(Budget $budget, User $user): array
    {
        $budget->loadMissing('destination');
        $destination = Country::findByName($budget->destination->country);

        if ($user->passport_country === null) {
            return ['passport_set' => false, 'destination_code' => $destination?->code, 'result' => null];
        }

        if ($destination === null) {
            return ['passport_set' => true, 'destination_code' => null, 'result' => null];
        }

        return [
            'passport_set' => true,
            'destination_code' => $destination->code,
            'result' => $this->check($user->passport_country, $destination->code),
        ];
    }

    public function find(string $passportCode, string $destinationCode): ?VisaRequirement
    {
        return VisaRequirement::with(['passport', 'destination'])
            ->where('passport_code', $passportCode)
            ->where('destination_code', $destinationCode)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function format(VisaRequirement $rule): array
    {
        return [
            'id' => $rule->id,
            'requirement' => $rule->requirement,
            'label' => $rule->label(),
            'needs_action' => $rule->needsActionBeforeTravel(),
            'summary' => $this->summary($rule),
            'max_stay_days' => $rule->max_stay_days,
            'conditions' => $rule->conditions,
            'required_documents' => $rule->required_documents ?? [],
            'passport_validity_months' => $rule->passport_validity_months,
            'fee_note' => $rule->fee_note,
            'processing_note' => $rule->processing_note,
            'transit_notes' => $rule->transit_notes,
            'official_url' => $rule->official_url,
            'source' => $rule->source,
            'verified' => $rule->isVerified(),
            'last_verified_at' => $rule->last_verified_at?->toDateString(),
            'is_stale' => $rule->isStale(),
        ];
    }

    /**
     * Warns when the traveler may need a transit visa for a stopover country.
     *
     * @return array<string, mixed>
     */
    private function transit(Country $passport, Country $transit): array
    {
        $rule = $passport->code === $transit->code ? null : $this->find($passport->code, $transit->code);
        $mayNeedVisa = $rule?->needsActionBeforeTravel() ?? false;

        $message = match (true) {
            $passport->code === $transit->code => "{$transit->name} is your own country, so the stopover needs no visa.",
            $rule === null => "We have no information for a stopover in {$transit->name}. Ask your airline before booking.",
            $mayNeedVisa => "You need a visa to enter {$transit->name} ({$rule->label()}). If you leave the airport or change airports, you will need it. Some airports also require an airport transit visa, so check with your airline.",
            default => "You can enter {$transit->name} without applying in advance ({$rule->label()}), so a stopover there should be straightforward.",
        };

        return [
            'country' => $this->country($transit),
            'requirement' => $rule?->requirement,
            'may_need_visa' => $mayNeedVisa,
            'message' => $message,
            'transit_notes' => $rule?->transit_notes,
        ];
    }

    private function summary(VisaRequirement $rule): string
    {
        $passport = $rule->passport?->name ?? $rule->passport_code;
        $destination = $rule->destination?->name ?? $rule->destination_code;
        $stay = $rule->max_stay_days ? " for up to {$rule->max_stay_days} days" : '';

        return match ($rule->requirement) {
            'visa_free' => "{$passport} passport holders can visit {$destination} without a visa{$stay}.",
            'visa_on_arrival' => "{$passport} passport holders can get a visa on arrival in {$destination}{$stay}.",
            'eta' => "{$passport} passport holders need an electronic travel authorisation (ETA) before flying to {$destination}{$stay}.",
            'e_visa' => "{$passport} passport holders must apply online for an e-visa before travelling to {$destination}{$stay}.",
            'visa_required' => "{$passport} passport holders need a visa from the embassy or consulate of {$destination} before travelling{$stay}.",
            'no_admission' => "{$destination} does not currently admit {$passport} passport holders.",
            default => $rule->label(),
        };
    }

    /**
     * @return array{code: string, name: string}
     */
    private function country(Country $country): array
    {
        return ['code' => $country->code, 'name' => $country->name];
    }
}
