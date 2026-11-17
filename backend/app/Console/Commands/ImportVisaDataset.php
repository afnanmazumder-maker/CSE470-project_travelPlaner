<?php

namespace App\Console\Commands;

use App\Services\VisaDatasetImporter;
use Illuminate\Console\Command;
use RuntimeException;

/** R6: refreshes layer 1 visa data from a Passport Index CSV without touching curated rows. */
class ImportVisaDataset extends Command
{
    protected $signature = 'visa:import
        {--requirements= : Path to a Passport Index tidy ISO-2 CSV (default: the shipped snapshot)}
        {--countries= : Path to a code,name countries CSV (default: the shipped list)}';

    protected $description = 'Import Passport Index visa requirements, keeping official and admin-edited rows';

    public function handle(VisaDatasetImporter $importer): int
    {
        try {
            $countries = $importer->importCountries($this->option('countries') ?: config('visa.dataset.countries_csv'));
            $stats = $importer->importRequirements($this->option('requirements') ?: config('visa.dataset.requirements_csv'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Countries: {$countries}");
        $this->info("Visa rules imported: {$stats['imported']}, curated kept: {$stats['kept_curated']}, skipped: {$stats['skipped']}");

        return self::SUCCESS;
    }
}
