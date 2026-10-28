<?php

namespace Tests\Feature;

use App\Models\Attraction;
use App\Models\User;
use Database\Seeders\DestinationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\Destination;

class RecommendationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DestinationSeeder::class);
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_recommendations_come_from_the_ai_service(): void
    {
        $this->user->interestWeights()->create(['tag' => 'history', 'weight' => 0.9]);
        $louvre = Attraction::where('name', 'Louvre Museum')->firstOrFail();

        Http::fake([
            '*/recommendations' => Http::response([
                'recommendations' => [[
                    'attraction_id' => $louvre->id,
                    'name' => 'Louvre Museum',
                    'score' => 0.9,
                    'reason' => 'Matches your interests: history',
                ]],
                'fallback_used' => false,
            ]),
        ]);

        $this->getJson('/api/recommendations')
            ->assertOk()
            ->assertJsonPath('fallback_used', false)
            ->assertJsonPath('ai_service_available', true)
            ->assertJsonPath('recommendations.0.destination.name', 'Paris');

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['interest_weights']['history'] == 0.9
                && count($body['attractions']) === 24;
        });
    }

    public function test_new_user_sends_an_empty_object_not_a_list(): void
    {
        Http::fake(['*' => Http::response(['recommendations' => [], 'fallback_used' => true])]);

        $this->getJson('/api/recommendations')->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->body(), '"interest_weights":{}'));
    }

    public function test_falls_back_to_popular_places_when_ai_service_is_down(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->getJson('/api/recommendations?limit=3')
            ->assertOk()
            ->assertJsonPath('fallback_used', true)
            ->assertJsonPath('ai_service_available', false)
            ->assertJsonCount(3, 'recommendations');
    }

    public function test_destination_filter_only_sends_that_destinations_attractions(): void
    {
        Http::fake(['*' => Http::response(['recommendations' => [], 'fallback_used' => true])]);
        $paris = Destination::where('name', 'Paris')->firstOrFail();

        $this->getJson("/api/recommendations?destination_id={$paris->id}")->assertOk();

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return count($body['attractions']) === 4;
        });
    }
    public function test_feedback_stores_the_weights_returned_by_the_ai_service(): void
    {
        $louvre = Attraction::where('name', 'Louvre Museum')->firstOrFail();

        Http::fake([
            '*/weights/update' => Http::response([
                'interest_weights' => ['museum' => 0.6, 'art' => 0.6, 'history' => 0.6],
            ]),
        ]);

        $this->postJson("/api/attractions/{$louvre->id}/feedback", ['action' => 'save'])
            ->assertOk();

        $this->assertDatabaseHas('interest_weights', ['user_id' => $this->user->id, 'tag' => 'art']);
    }

    public function test_feedback_reports_503_when_ai_service_is_down(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        $louvre = Attraction::where('name', 'Louvre Museum')->firstOrFail();

        $this->postJson("/api/attractions/{$louvre->id}/feedback", ['action' => 'save'])
            ->assertStatus(503);
    }

    public function test_feedback_rejects_unknown_actions(): void
    {
        $louvre = Attraction::where('name', 'Louvre Museum')->firstOrFail();

        $this->postJson("/api/attractions/{$louvre->id}/feedback", ['action' => 'like'])
            ->assertStatus(422);
    }
}