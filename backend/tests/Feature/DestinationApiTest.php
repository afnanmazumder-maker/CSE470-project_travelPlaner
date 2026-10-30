<?php

namespace Tests\Feature;

use App\Models\Destination;
use Database\Seeders\DestinationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestinationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DestinationSeeder::class);
    }

    public function test_lists_all_destinations(): void
    {
        $this->getJson('/api/destinations')
            ->assertOk()
            ->assertJsonCount(6, 'data');
    }

    public function test_search_by_name_is_case_insensitive(): void
    {
        $this->getJson('/api/destinations?q=PAR')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Paris');
    }

    public function test_filter_by_theme(): void
    {
        $this->getJson('/api/destinations?theme=beach')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bali');
    }

    public function test_filter_by_best_month(): void
    {
        $this->getJson('/api/destinations?month=12')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bangkok');
    }

    public function test_filters_can_be_combined(): void
    {
        $this->getJson('/api/destinations?region=Asia&budget_level=budget')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Bali');
    }

    public function test_filter_by_minimum_safety(): void
    {
        $this->getJson('/api/destinations?min_safety=4')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_invalid_month_is_rejected(): void
    {
        $this->getJson('/api/destinations?month=13')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month']);
    }

    public function test_show_returns_destination_with_attractions(): void
    {
        $paris = Destination::where('name', 'Paris')->firstOrFail();

        $this->getJson("/api/destinations/{$paris->id}")
            ->assertOk()
            ->assertJsonPath('name', 'Paris')
            ->assertJsonCount(4, 'attractions');
    }

    public function test_show_unknown_destination_is_404(): void
    {
        $this->getJson('/api/destinations/9999')->assertNotFound();
    }
}