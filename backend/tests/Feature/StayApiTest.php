<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Destination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StayApiTest extends TestCase
{
    use RefreshDatabase;

    private Destination $testville;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());

        $this->testville = $this->destination('Testville', 10.0, 10.0);
        $elsewhere = $this->destination('Othertown', 20.0, 20.0);

        // 0.045 degrees of latitude is about 5 km; 0.18 is about 20 km.
        $this->stay($this->testville, 'Central Pool Hotel', 10.0, 10.0, 100, 4.5, ['wifi', 'pool'], 'hotel');
        $this->stay($this->testville, 'Budget Inn', 10.045, 10.0, 50, 3.9, ['wifi'], 'hostel');
        $this->stay($this->testville, 'Far Resort', 10.18, 10.0, 80, 4.1, ['wifi', 'pool', 'spa'], 'resort');
        $this->stay($elsewhere, 'Elsewhere Hotel', 20.0, 20.0, 60, 4.8, ['wifi', 'pool'], 'hotel');
    }

    private function destination(string $name, float $lat, float $lng): Destination
    {
        return Destination::create([
            'name' => $name, 'country' => 'Testland', 'region' => 'Asia', 'overview' => 'Test.', 'budget_level' => 'moderate',
            'cost_index' => 50, 'climate' => 'temperate', 'best_months' => [1], 'themes' => ['food'],
            'safety_rating' => 4, 'latitude' => $lat, 'longitude' => $lng,
        ]);
    }

    private function stay(Destination $destination, string $name, float $lat, float $lng, float $price, float $rating, array $amenities, string $type): void
    {
        Accommodation::create([
            'destination_id' => $destination->id, 'name' => $name, 'property_type' => $type, 'price_per_night' => $price,
            'rating' => $rating, 'review_count' => 10, 'latitude' => $lat, 'longitude' => $lng, 'address' => $destination->name,
            'amenities' => $amenities,
        ]);
    }

    /** @return list<string> */
    private function names(array $query): array
    {
        return collect($this->getJson('/api/stays/search?'.http_build_query($query))->assertOk()->json('results'))->pluck('name')->all();
    }

    public function test_every_selected_amenity_must_match(): void
    {
        $this->assertSame(['Far Resort', 'Central Pool Hotel'], $this->names(['destination_id' => $this->testville->id, 'amenities' => ['wifi', 'pool']]));
    }

    public function test_rating_type_and_distance_filters(): void
    {
        $id = $this->testville->id;

        $this->assertSame(['Far Resort', 'Central Pool Hotel'], $this->names(['destination_id' => $id, 'min_rating' => 4]));
        $this->assertSame(['Budget Inn'], $this->names(['destination_id' => $id, 'property_types' => ['hostel']]));
        $this->assertSame(['Budget Inn', 'Central Pool Hotel'], $this->names(['destination_id' => $id, 'max_distance_km' => 10]));
    }

    public function test_distance_and_total_price_for_the_stay_dates(): void
    {
        $budgetInn = collect($this->getJson("/api/stays/search?destination_id={$this->testville->id}&check_in=2026-12-01&check_out=2026-12-04")->json('results'))
            ->firstWhere('name', 'Budget Inn');

        $this->assertEqualsWithDelta(5.0, $budgetInn['distance_km'], 0.1);
        $this->assertSame(150, (int) $budgetInn['total_price']);
    }

    public function test_map_bounds_search(): void
    {
        $this->assertSame(['Budget Inn'], $this->names(['north' => 10.1, 'south' => 10.01, 'east' => 10.05, 'west' => 9.95]));

        $this->getJson('/api/stays/search?north=10&south=11&east=10&west=9')->assertUnprocessable()->assertJsonValidationErrors('south');
    }

    public function test_a_destination_or_map_area_is_required(): void
    {
        $this->getJson('/api/stays/search')->assertUnprocessable()->assertJsonValidationErrors('destination_id');
    }
}
