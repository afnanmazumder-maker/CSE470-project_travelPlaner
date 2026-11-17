<?php

namespace Tests\Unit;

use App\Services\VisaDatasetImporter;
use PHPUnit\Framework\TestCase;

class VisaDatasetImporterTest extends TestCase
{
    public function test_a_number_means_visa_free_for_that_many_days(): void
    {
        $this->assertSame(['requirement' => 'visa_free', 'max_stay_days' => 90], (new VisaDatasetImporter)->parseRequirement('90'));
    }

    public function test_dataset_words_map_to_requirement_types(): void
    {
        $importer = new VisaDatasetImporter;

        $this->assertSame('visa_on_arrival', $importer->parseRequirement('visa on arrival')['requirement']);
        $this->assertSame('eta', $importer->parseRequirement('eta')['requirement']);
        $this->assertSame('e_visa', $importer->parseRequirement(' E-Visa ')['requirement']);
        $this->assertSame('visa_required', $importer->parseRequirement('visa required')['requirement']);
        $this->assertSame('no_admission', $importer->parseRequirement('no admission')['requirement']);
    }

    public function test_same_country_and_unknown_values_are_skipped(): void
    {
        $importer = new VisaDatasetImporter;

        $this->assertNull($importer->parseRequirement('-1'));
        $this->assertNull($importer->parseRequirement('covid ban'));
    }
}
