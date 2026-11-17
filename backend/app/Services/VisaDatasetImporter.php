<?php

namespace App\Services;

use App\Models\Country;
use App\Models\VisaRequirement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * R6 layer 1: loads the Passport Index CSV into countries and visa_requirements.
 *
 * Rows that were checked by hand (source = official) or edited by an admin (source = admin)
 * are never overwritten, so re-importing a newer dataset keeps the curated details.
 */
class VisaDatasetImporter
{
    private const CHUNK_SIZE = 1000;

    /**
     * Imports the code,name list of countries.
     */
    public function importCountries(string $path): int
    {
        $rows = collect($this->readCsv($path, ['code', 'name']))
            ->map(fn (array $row): array => ['code' => strtoupper($row['code']), 'name' => $row['name']])
            ->all();

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            Country::upsert($chunk, ['code'], ['name']);
        }

        return count($rows);
    }

    /**
     * Imports Passport,Destination,Requirement rows.
     *
     * @return array{imported: int, kept_curated: int, skipped: int}
     */
    public function importRequirements(string $path): array
    {
        $curated = VisaRequirement::where('source', '!=', 'dataset')
            ->get(['passport_code', 'destination_code'])
            ->map(fn (VisaRequirement $row): string => $row->passport_code.'-'.$row->destination_code)
            ->flip();

        $knownCountries = Country::pluck('code')->flip();
        $updatedOn = config('visa.dataset.updated_on');
        $now = now();
        $stats = ['imported' => 0, 'kept_curated' => 0, 'skipped' => 0];
        $batch = [];

        foreach ($this->readCsv($path, ['Passport', 'Destination', 'Requirement']) as $row) {
            $passport = strtoupper($row['Passport']);
            $destination = strtoupper($row['Destination']);
            $parsed = $this->parseRequirement($row['Requirement']);

            if ($parsed === null || ! isset($knownCountries[$passport], $knownCountries[$destination])) {
                $stats['skipped']++;

                continue;
            }

            if (isset($curated[$passport.'-'.$destination])) {
                $stats['kept_curated']++;

                continue;
            }

            $batch[] = [
                'passport_code' => $passport,
                'destination_code' => $destination,
                'requirement' => $parsed['requirement'],
                'max_stay_days' => $parsed['max_stay_days'],
                'source' => 'dataset',
                'last_verified_at' => $updatedOn,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) === self::CHUNK_SIZE) {
                $stats['imported'] += $this->upsertRequirements($batch);
                $batch = [];
            }
        }

        $stats['imported'] += $this->upsertRequirements($batch);

        return $stats;
    }

    /**
     * Turns a dataset value such as "90", "e-visa" or "visa required" into our requirement type.
     * Returns null for "-1" (same country) and for values we do not recognise.
     *
     * @return array{requirement: string, max_stay_days: ?int}|null
     */
    public function parseRequirement(string $value): ?array
    {
        $value = strtolower(trim($value));

        if (ctype_digit($value)) {
            return ['requirement' => 'visa_free', 'max_stay_days' => (int) $value];
        }

        $requirement = match ($value) {
            'visa free' => 'visa_free',
            'visa on arrival' => 'visa_on_arrival',
            'eta' => 'eta',
            'e-visa' => 'e_visa',
            'visa required' => 'visa_required',
            'no admission' => 'no_admission',
            default => null,
        };

        return $requirement === null ? null : ['requirement' => $requirement, 'max_stay_days' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     */
    private function upsertRequirements(array $batch): int
    {
        if ($batch === []) {
            return 0;
        }

        DB::table('visa_requirements')->upsert(
            $batch,
            ['passport_code', 'destination_code'],
            ['requirement', 'max_stay_days', 'source', 'last_verified_at', 'updated_at'],
        );

        return count($batch);
    }

    /**
     * Reads a CSV with a header row and yields each row keyed by the expected columns.
     *
     * @param  list<string>  $columns
     * @return \Generator<int, array<string, string>>
     */
    private function readCsv(string $path, array $columns): \Generator
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Visa dataset file not found: {$path}");
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, escape: '');

        if ($header !== $columns) {
            fclose($handle);

            throw new RuntimeException('Unexpected columns in '.basename($path).'. Expected: '.implode(',', $columns));
        }

        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if (count($values) === count($columns)) {
                yield array_combine($columns, $values);
            }
        }

        fclose($handle);
    }
}
