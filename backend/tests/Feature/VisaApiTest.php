<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Destination;
use App\Models\User;
use App\Models\VisaRequirement;
use App\Services\VisaDatasetImporter;
use Database\Seeders\DestinationSeeder;
use Database\Seeders\VisaOfficialRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(VisaDatasetImporter::class)->importCountries(config('visa.dataset.countries_csv'));
        $this->seed(VisaOfficialRuleSeeder::class);
    }

    public function test_lists_countries_with_the_default_passport(): void
    {
        $response = $this->getJson('/api/visa/countries')->assertOk()->assertJsonPath('default_passport', 'BD');

        $this->assertCount(199, $response->json('countries'));
        $this->assertContains(['code' => 'BD', 'name' => 'Bangladesh'], $response->json('countries'));
    }

    public function test_bangladesh_to_thailand_shows_the_checked_official_details(): void
    {
        $this->getJson('/api/visa/check?passport=BD&destination=TH')
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('requirement', 'e_visa')
            ->assertJsonPath('needs_action', true)
            ->assertJsonPath('source', 'official')
            ->assertJsonPath('verified', true)
            ->assertJsonPath('official_url', 'https://www.thaievisa.go.th')
            ->assertJsonPath('last_verified_at', '2026-11-13')
            ->assertJsonPath('disclaimer', config('visa.disclaimer'));
    }

    public function test_codes_are_accepted_in_lowercase(): void
    {
        $this->getJson('/api/visa/check?passport=bd&destination=id')
            ->assertOk()
            ->assertJsonPath('max_stay_days', 60)
            ->assertJsonPath('passport_validity_months', 6);
    }

    public function test_a_dataset_row_says_it_is_not_checked_by_hand(): void
    {
        VisaRequirement::create([
            'passport_code' => 'BD', 'destination_code' => 'MV', 'requirement' => 'visa_on_arrival',
            'max_stay_days' => 30, 'source' => 'dataset', 'last_verified_at' => now()->subDays(10)->toDateString(),
        ]);

        $this->getJson('/api/visa/check?passport=BD&destination=MV')
            ->assertOk()
            ->assertJsonPath('verified', false)
            ->assertJsonPath('is_stale', false)
            ->assertJsonPath('required_documents', [])
            ->assertJsonPath('summary', 'Bangladesh passport holders can get a visa on arrival in Maldives for up to 30 days.');
    }

    public function test_an_old_check_is_flagged_as_stale(): void
    {
        VisaRequirement::where('destination_code', 'JP')->update(['last_verified_at' => now()->subDays(400)->toDateString()]);

        $this->getJson('/api/visa/check?passport=BD&destination=JP')->assertOk()->assertJsonPath('is_stale', true);
    }

    public function test_own_country_and_missing_rules_are_explained(): void
    {
        $this->getJson('/api/visa/check?passport=BD&destination=BD')
            ->assertOk()
            ->assertJsonPath('own_country', true)
            ->assertJsonPath('requirement', null);

        $this->getJson('/api/visa/check?passport=US&destination=TH')
            ->assertOk()
            ->assertJsonPath('found', false)
            ->assertJsonPath('requirement', null);
    }

    public function test_a_stopover_country_that_needs_a_visa_is_flagged(): void
    {
        $this->getJson('/api/visa/check?passport=BD&destination=TH&transit=FR')
            ->assertOk()
            ->assertJsonPath('transit.country.name', 'France')
            ->assertJsonPath('transit.may_need_visa', true);
    }

    public function test_unknown_countries_are_rejected(): void
    {
        $this->getJson('/api/visa/check?passport=XX&destination=TH')->assertUnprocessable()->assertJsonValidationErrors('passport');
        $this->getJson('/api/visa/check?passport=BD')->assertUnprocessable()->assertJsonValidationErrors('destination');
        $this->getJson('/api/visa/check?passport=BD&destination=TH&transit=TH')->assertUnprocessable()->assertJsonValidationErrors('transit');
    }

    public function test_the_traveler_saves_a_passport(): void
    {
        $this->putJson('/api/visa/passport', ['passport_country' => 'BD'])->assertUnauthorized();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/visa/passport')->assertOk()->assertJsonPath('passport_country', null);
        $this->putJson('/api/visa/passport', ['passport_country' => 'xx'])->assertUnprocessable();
        $this->putJson('/api/visa/passport', ['passport_country' => 'bd'])->assertOk()->assertJsonPath('passport_country', 'BD');
        $this->assertSame('BD', $user->fresh()->passport_country);
    }

    public function test_a_trip_warns_when_the_destination_needs_a_visa(): void
    {
        $this->seed(DestinationSeeder::class);
        $user = User::factory()->create();
        $budget = $this->budgetFor($user, 'Tokyo');
        Sanctum::actingAs($user);

        $this->getJson("/api/budgets/{$budget->id}/visa")
            ->assertOk()
            ->assertJsonPath('passport_set', false)
            ->assertJsonPath('destination_code', 'JP');

        $user->forceFill(['passport_country' => 'BD'])->save();

        $this->getJson("/api/budgets/{$budget->id}/visa")
            ->assertOk()
            ->assertJsonPath('passport_set', true)
            ->assertJsonPath('result.requirement', 'visa_required')
            ->assertJsonPath('result.needs_action', true);
    }

    public function test_a_trip_visa_warning_is_private_to_its_owner(): void
    {
        $this->seed(DestinationSeeder::class);
        $budget = $this->budgetFor(User::factory()->create(), 'Bangkok');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/budgets/{$budget->id}/visa")->assertForbidden();
    }

    public function test_visa_data_is_admin_only(): void
    {
        $this->getJson('/api/admin/visa-requirements')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/admin/visa-requirements')->assertForbidden();
        $this->postJson('/api/admin/visa-requirements', [])->assertForbidden();
    }

    public function test_an_admin_adds_edits_and_deletes_a_rule(): void
    {
        $this->actingAsAdmin();

        $id = $this->postJson('/api/admin/visa-requirements', [
            'passport_code' => 'bd', 'destination_code' => 'np', 'requirement' => 'visa_on_arrival',
            'max_stay_days' => 30, 'required_documents' => ['Passport valid 6 months'],
            'official_url' => 'https://www.immigration.gov.np',
        ])
            ->assertCreated()
            ->assertJsonPath('source', 'admin')
            ->assertJsonPath('destination_name', 'Nepal')
            ->assertJsonPath('last_verified_at', now()->toDateString())
            ->json('id');

        $this->postJson('/api/admin/visa-requirements', [
            'passport_code' => 'BD', 'destination_code' => 'NP', 'requirement' => 'visa_free',
        ])->assertUnprocessable()->assertJsonValidationErrors('destination_code');

        $this->putJson("/api/admin/visa-requirements/{$id}", [
            'passport_code' => 'BD', 'destination_code' => 'NP', 'requirement' => 'visa_free', 'max_stay_days' => 90,
        ])->assertOk()->assertJsonPath('requirement', 'visa_free')->assertJsonPath('max_stay_days', 90);

        $this->deleteJson("/api/admin/visa-requirements/{$id}")->assertOk();
        $this->assertDatabaseMissing('visa_requirements', ['id' => $id]);
    }

    public function test_admins_filter_rules_and_see_counts(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/admin/visa-requirements?passport=BD&source=official')
            ->assertOk()
            ->assertJsonPath('total', 6)
            ->assertJsonPath('counts.official', 6)
            ->assertJsonPath('counts.stale', 0)
            ->assertJsonPath('data.0.passport_name', 'Bangladesh');
    }

    public function test_reimporting_the_dataset_keeps_hand_checked_rows(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'visa');
        file_put_contents($csv, "Passport,Destination,Requirement\nBD,TH,visa required\nBD,MY,30\nBD,BD,-1\nBD,NP,covid ban\n");

        $stats = app(VisaDatasetImporter::class)->importRequirements($csv);
        unlink($csv);

        $this->assertSame(['imported' => 1, 'kept_curated' => 1, 'skipped' => 2], $stats);
        $this->assertDatabaseHas('visa_requirements', ['passport_code' => 'BD', 'destination_code' => 'TH', 'requirement' => 'e_visa', 'source' => 'official']);
        $this->assertDatabaseHas('visa_requirements', ['passport_code' => 'BD', 'destination_code' => 'MY', 'requirement' => 'visa_free', 'max_stay_days' => 30, 'source' => 'dataset']);
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function budgetFor(User $user, string $city): Budget
    {
        return $user->budgets()->create([
            'destination_id' => Destination::where('name', $city)->value('id'),
            'title' => "{$city} trip",
            'days' => 5,
            'travelers' => 1,
            'comfort' => 'standard',
            'planned' => ['food' => 100],
        ]);
    }
}
