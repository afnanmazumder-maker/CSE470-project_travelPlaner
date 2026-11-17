<?php

namespace Database\Seeders;

use App\Services\VisaDatasetImporter;
use Illuminate\Database\Seeder;

/**
 * R6: loads all countries and about 39,000 passport/destination rules from the
 * Passport Index snapshot, then adds the hand-checked official details.
 *
 * Run: php artisan db:seed --class=VisaSeeder
 */
class VisaSeeder extends Seeder
{
    public function run(VisaDatasetImporter $importer): void
    {
        $countries = $importer->importCountries(config('visa.dataset.countries_csv'));
        $this->command?->info("Countries: {$countries}");

        $this->call(VisaOfficialRuleSeeder::class);

        $stats = $importer->importRequirements(config('visa.dataset.requirements_csv'));
        $this->command?->info("Visa rules imported: {$stats['imported']}, curated kept: {$stats['kept_curated']}, skipped: {$stats['skipped']}");
    }
}
