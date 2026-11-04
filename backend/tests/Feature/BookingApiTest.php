<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Budget;
use App\Models\Destination;
use App\Models\User;
use Database\Seeders\DestinationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Budget $trip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DestinationSeeder::class);
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        $this->trip = $this->user->budgets()->create([
            'title' => 'Bali trip', 'destination_id' => Destination::where('name', 'Bali')->value('id'),
            'days' => 7, 'travelers' => 2, 'comfort' => 'moderate', 'planned' => ['stay' => 700],
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'stay', 'budget_id' => $this->trip->id, 'title' => 'Ubud Rice Terrace Villa',
            'confirmation_number' => 'URT-1', 'start_at' => '2026-12-01 15:00', 'end_at' => '2026-12-04 11:00',
            'status' => 'planned', 'price' => 540, 'cancellation_deadline' => '2026-11-28 18:00',
        ], $overrides);
    }

    public function test_booking_routes_require_login(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/bookings')->assertUnauthorized();
    }

    public function test_store_saves_a_booking_on_the_trip(): void
    {
        $this->postJson('/api/bookings', $this->payload())
            ->assertCreated()
            ->assertJsonPath('budget.title', 'Bali trip')
            ->assertJsonPath('start_at', '2026-12-01T15:00')
            ->assertJsonPath('cancellation_deadline', '2026-11-28T18:00');

        $this->assertDatabaseHas('bookings', ['user_id' => $this->user->id, 'budget_id' => $this->trip->id, 'confirmation_number' => 'URT-1']);
    }

    public function test_status_and_confirmation_can_be_updated_and_filtered(): void
    {
        $id = $this->postJson('/api/bookings', $this->payload())->json('id');

        $this->putJson("/api/bookings/{$id}", $this->payload(['status' => 'confirmed', 'confirmation_number' => 'URT-99']))
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');

        $this->getJson('/api/bookings?status=confirmed')->assertOk()->assertJsonCount(1)->assertJsonPath('0.confirmation_number', 'URT-99');
        $this->getJson('/api/bookings?type=flight')->assertOk()->assertJsonCount(0);
    }

    public function test_other_travelers_cannot_see_change_or_delete_a_booking(): void
    {
        $id = $this->postJson('/api/bookings', $this->payload())->json('id');

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/bookings')->assertOk()->assertJsonCount(0);
        $this->getJson("/api/bookings/{$id}")->assertForbidden();
        $this->putJson("/api/bookings/{$id}", $this->payload())->assertForbidden();
        $this->deleteJson("/api/bookings/{$id}")->assertForbidden();
        $this->assertDatabaseHas('bookings', ['id' => $id]);
    }

    public function test_a_booking_cannot_be_added_to_someone_elses_trip(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', $this->payload())->assertForbidden();
        $this->assertSame(0, Booking::count());
    }

    public function test_validation_rejects_late_cancellation_deadlines_and_mismatched_links(): void
    {
        $this->postJson('/api/bookings', $this->payload(['cancellation_deadline' => '2026-12-02 10:00']))
            ->assertUnprocessable()->assertJsonValidationErrors('cancellation_deadline');
        $this->postJson('/api/bookings', $this->payload(['flight_id' => 1]))
            ->assertUnprocessable()->assertJsonValidationErrors('flight_id');
        $this->postJson('/api/bookings', $this->payload(['status' => 'maybe']))
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }
}
