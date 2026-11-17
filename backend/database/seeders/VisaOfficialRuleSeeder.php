<?php

namespace Database\Seeders;

use App\Models\VisaRequirement;
use Illuminate\Database\Seeder;

/**
 * R6 layer 2: details checked by hand on official or embassy sites for Bangladeshi
 * passport holders visiting the six destinations in the app (checked 13 Nov 2026).
 * Fields we could not confirm are left empty rather than guessed.
 */
class VisaOfficialRuleSeeder extends Seeder
{
    private const CHECKED_ON = '2026-11-13';

    public function run(): void
    {
        foreach ($this->rules() as $destination => $rule) {
            VisaRequirement::updateOrCreate(
                ['passport_code' => 'BD', 'destination_code' => $destination],
                $rule + ['source' => 'official', 'last_verified_at' => self::CHECKED_ON],
            );
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rules(): array
    {
        $schengenDocuments = [
            'Passport valid at least 3 months after you leave the Schengen area, issued in the last 10 years, with 2 blank pages',
            'Completed Schengen short-stay visa application form',
            'Recent passport photos',
            'Travel medical insurance of at least EUR 30,000, valid in every Schengen country for the whole trip plus 15 days',
            'Return flight booking',
            'Hotel bookings or an invitation letter',
            'Recent bank statements showing enough money for the trip',
            'Employment letter, business documents or proof of studies',
        ];

        $schengenConditions = 'Short-stay Schengen visa (type C): up to 90 days in any 180-day period across the whole Schengen area. Apply to the consulate of the country where you will spend the most nights.';

        $schengenTransit = 'If you connect through a Schengen airport without leaving the international zone, check whether your passport needs an airport transit visa (type A) for that country.';

        return [
            'FR' => [
                'requirement' => 'visa_required',
                'max_stay_days' => 90,
                'conditions' => $schengenConditions,
                'required_documents' => $schengenDocuments,
                'passport_validity_months' => 3,
                'transit_notes' => $schengenTransit,
                'official_url' => 'https://france-visas.gouv.fr',
            ],
            'IT' => [
                'requirement' => 'visa_required',
                'max_stay_days' => 90,
                'conditions' => $schengenConditions,
                'required_documents' => $schengenDocuments,
                'passport_validity_months' => 3,
                'transit_notes' => $schengenTransit,
                'official_url' => 'https://vistoperitalia.esteri.it',
            ],
            'JP' => [
                'requirement' => 'visa_required',
                'conditions' => 'Temporary visitor (sightseeing) visa from the Embassy of Japan in Bangladesh. The embassy may ask for documents that are not on this list.',
                'required_documents' => [
                    'Valid passport, plus any old passports',
                    'Two photos, 3.5 x 4.5 cm, taken in the last 6 months',
                    'Completed visa application form',
                    'Proof of funds, such as a personal bank statement',
                    'Employment certificate (school ID for students)',
                    'Day-by-day travel plan for Japan',
                    'Return flight booking',
                ],
                'official_url' => 'https://www.bd.emb-japan.go.jp/en/visa/visareqdoc.html',
            ],
            'TR' => [
                'requirement' => 'visa_required',
                'max_stay_days' => 30,
                'conditions' => 'An e-visa may be available at evisa.gov.tr: reports from January 2024 describe a single-entry e-visa for Bangladeshi citizens, valid 180 days with stays of up to 30 days. For some nationalities Turkey only offers the e-visa to holders of a valid Schengen, US, UK or Ireland visa or residence permit, so check on the site whether you qualify. Otherwise apply to the Turkish embassy.',
                'required_documents' => [
                    'Passport valid at least 6 months',
                    'Return flight booking',
                    'Hotel bookings',
                    'Proof of funds for the trip',
                    'Email address and a debit or credit card for the online fee',
                ],
                'passport_validity_months' => 6,
                'official_url' => 'https://www.evisa.gov.tr',
            ],
            'TH' => [
                'requirement' => 'e_visa',
                'conditions' => 'Apply online through the Thai e-Visa portal. You do not hand in your passport: the approved e-visa arrives by email and you show the printout to immigration on arrival.',
                'required_documents' => [
                    'Passport (scanned and uploaded)',
                    'Completed online application with the supporting documents it asks for',
                    'Visa fee paid by bank transfer using the payment summary the portal gives you (cash at the bank is not accepted)',
                    'Printed approved e-visa for arrival',
                ],
                'processing_note' => 'About 10 working days (Thai embassy estimate, January 2025)',
                'official_url' => 'https://www.thaievisa.go.th',
            ],
            'ID' => [
                'requirement' => 'e_visa',
                'max_stay_days' => 60,
                'conditions' => 'C1 Tourism Visitor Visa, single entry. The first stay is up to 60 days and can be extended online to 180 days in total. Use the visa within 90 days of issue. Selling goods or services and paid work are not allowed. Citizens of some countries need an Indonesian sponsor; the portal tells you if this applies.',
                'required_documents' => [
                    'Passport valid at least 6 months',
                    'Bank statement for the last 3 months showing at least USD 2,000 or the equivalent',
                    'Recent colour photo that meets the official photo rules',
                ],
                'passport_validity_months' => 6,
                'fee_note' => 'IDR 1,000,000',
                'processing_note' => '5 working days after payment',
                'official_url' => 'https://evisa.imigrasi.go.id',
            ],
        ];
    }
}
