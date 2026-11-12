<?php

namespace Tests\Feature;

use App\Events\FlightPriceDropped;
use App\Mail\PriceDropMail;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FlightApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());

        $this->flight(['flight_number' => 'A-DIRECT', 'price' => 200, 'stops' => 0, 'depart_at' => '2026-12-05 10:00', 'duration_minutes' => 150]);
        $this->flight(['flight_number' => 'B-1STOP', 'price' => 150, 'stops' => 1, 'stopover' => 'KUL', 'depart_at' => '2026-12-05 22:00', 'duration_minutes' => 420]);
        $this->flight(['flight_number' => 'C-BIZ', 'price' => 600, 'cabin_class' => 'business']);
        $this->flight(['flight_number' => 'D-OTHERDAY', 'price' => 90, 'depart_at' => '2026-12-07 10:00']);
        $this->flight(['flight_number' => 'E-LASTSEAT', 'price' => 100, 'seats_available' => 1]);
    }

    private function flight(array $overrides = []): Flight
    {
        return Flight::create(array_merge([
            'airline' => 'Test Air', 'airline_code' => 'TA', 'flight_number' => 'TA100',
            'origin' => 'DAC', 'destination' => 'BKK',
            'depart_at' => '2026-12-05 10:00', 'arrive_at' => '2026-12-05 13:30',
            'duration_minutes' => 150, 'stops' => 0, 'cabin_class' => 'economy',
            'price' => 200, 'seats_available' => 9,
        ], $overrides));
    }

    private function search(array $query = []): TestResponse
    {
        return $this->getJson('/api/flights/search?'.http_build_query(array_merge([
            'origin' => 'dac', 'destination' => 'BKK', 'date' => '2026-12-05', 'passengers' => 2, 'cabin_class' => 'economy',
        ], $query)));
    }

    public function test_flight_routes_require_login(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/flights/options')->assertUnauthorized();
    }

    public function test_search_returns_matching_flights_with_enough_seats_sorted_by_price(): void
    {
        $this->search()
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('results.0.flight_number', 'B-1STOP')
            ->assertJsonPath('results.0.total_price', 300)
            ->assertJsonPath('results.0.depart_at', '2026-12-05T22:00')
            ->assertJsonPath('results.1.flight_number', 'A-DIRECT');
    }

    public function test_filters_and_duration_sort(): void
    {
        $this->search(['max_stops' => 0])->assertJsonPath('count', 1)->assertJsonPath('results.0.flight_number', 'A-DIRECT');
        $this->search(['max_price' => 160])->assertJsonPath('count', 1)->assertJsonPath('results.0.flight_number', 'B-1STOP');
        $this->search(['depart_windows' => ['evening']])->assertJsonPath('count', 1)->assertJsonPath('results.0.depart_window', 'evening');
        $this->search(['sort' => 'duration'])->assertJsonPath('results.0.flight_number', 'A-DIRECT');
    }

    public function test_search_validation(): void
    {
        $this->search(['destination' => 'DAC'])->assertUnprocessable()->assertJsonValidationErrors('destination');
        $this->search(['passengers' => 12])->assertUnprocessable()->assertJsonValidationErrors('passengers');
        $this->search(['cabin_class' => 'luxury'])->assertUnprocessable()->assertJsonValidationErrors('cabin_class');
    }

    public function test_multi_city_totals_the_cheapest_legs_and_checks_date_order(): void
    {
        $this->flight(['flight_number' => 'LEG2', 'origin' => 'BKK', 'destination' => 'DPS', 'price' => 190, 'depart_at' => '2026-12-08 09:30']);
        $legs = [
            ['origin' => 'DAC', 'destination' => 'BKK', 'date' => '2026-12-05'],
            ['origin' => 'BKK', 'destination' => 'DPS', 'date' => '2026-12-08'],
        ];

        // One passenger, so the flight with a single seat left (100) qualifies.
        $this->postJson('/api/flights/multi-city', ['legs' => $legs, 'passengers' => 1, 'cabin_class' => 'economy'])
            ->assertOk()
            ->assertJsonPath('legs.0.cheapest_total', 100)
            ->assertJsonPath('legs.1.cheapest_total', 190)
            ->assertJsonPath('cheapest_trip_total', 290);

        $this->postJson('/api/flights/multi-city', ['legs' => array_reverse($legs), 'passengers' => 1, 'cabin_class' => 'economy'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('legs.1.date');
    }

    public function test_price_calendar_covers_the_month_and_finds_the_cheapest_day(): void
    {
        $calendar = $this->getJson('/api/flights/price-calendar?origin=DAC&destination=BKK&cabin_class=economy&month=2026-12')
            ->assertOk()
            ->assertJsonCount(31, 'days')
            ->assertJsonPath('cheapest.date', '2026-12-07')
            ->json();

        $this->assertSame(100.0, (float) collect($calendar['days'])->firstWhere('date', '2026-12-05')['min_price']);
        $this->assertNull(collect($calendar['days'])->firstWhere('date', '2026-12-20')['min_price']);
    }

    public function test_lowering_a_fare_announces_one_price_drop(): void
    {
        Event::fake([FlightPriceDropped::class]);

        $this->postJson('/api/price-alerts', ['origin' => 'DAC', 'destination' => 'BKK', 'depart_date' => '2026-12-05', 'cabin_class' => 'economy'])
            ->assertCreated()
            ->assertJsonPath('alert.last_seen_price', 100);

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        Sanctum::actingAs($admin);
        $cheapest = Flight::where('flight_number', 'E-LASTSEAT')->firstOrFail();
        $payload = fn (float $price): array => array_merge($cheapest->only(['airline', 'airline_code', 'flight_number', 'origin', 'destination', 'duration_minutes', 'stops', 'stopover', 'cabin_class', 'seats_available']), [
            'depart_at' => $cheapest->depart_at->toDateTimeString(), 'arrive_at' => $cheapest->arrive_at->toDateTimeString(), 'price' => $price,
        ]);

        $this->putJson("/api/admin/flights/{$cheapest->id}", $payload(85))->assertOk()->assertJsonCount(1, 'price_alerts_announced');
        $this->putJson("/api/admin/flights/{$cheapest->id}", $payload(85))->assertOk()->assertJsonCount(0, 'price_alerts_announced');
        $this->putJson("/api/admin/flights/{$cheapest->id}", $payload(95))->assertOk()->assertJsonCount(0, 'price_alerts_announced');

        Event::assertDispatchedTimes(FlightPriceDropped::class, 1);
        Event::assertDispatched(FlightPriceDropped::class, fn (FlightPriceDropped $event) => $event->price === 85.0 && $event->previousPrice === 100.0);
    }

    public function test_an_alert_already_at_its_target_announces_immediately(): void
    {
        Event::fake([FlightPriceDropped::class]);

        $this->postJson('/api/price-alerts', ['origin' => 'DAC', 'destination' => 'BKK', 'cabin_class' => 'economy', 'target_price' => 120])
            ->assertCreated()
            ->assertJsonCount(1, 'announced');

        Event::assertDispatchedTimes(FlightPriceDropped::class, 1);
    }

    public function test_a_price_drop_emails_the_traveler_who_watches_the_route(): void
    {
        Mail::fake();
        $traveler = User::factory()->create();
        Sanctum::actingAs($traveler);

        $this->postJson('/api/price-alerts', ['origin' => 'DAC', 'destination' => 'BKK', 'cabin_class' => 'economy', 'target_price' => 120])
            ->assertCreated();

        Mail::assertSent(PriceDropMail::class, fn (PriceDropMail $mail): bool => $mail->hasTo($traveler->email)
            && $mail->price === 90.0
            && str_contains($mail->envelope()->subject, 'DAC → BKK fares dropped to $90.00'));
        $this->getJson('/api/price-alerts')->assertJsonPath('0.last_notified_price', 90);
    }

    public function test_alerts_are_private_to_their_owner(): void
    {
        $id = $this->postJson('/api/price-alerts', ['origin' => 'DAC', 'destination' => 'BKK', 'cabin_class' => 'economy'])->json('alert.id');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/price-alerts')->assertOk()->assertJsonCount(0);
        $this->deleteJson("/api/price-alerts/{$id}")->assertNotFound();
    }

    public function test_flight_data_is_admin_only(): void
    {
        $this->getJson('/api/admin/flights')->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/flights?origin=DAC')->assertOk()->assertJsonPath('total', 5);
        $this->postJson('/api/admin/flights', [
            'airline' => 'Test Air', 'airline_code' => 'TA', 'flight_number' => 'TA9', 'origin' => 'DAC', 'destination' => 'BKK',
            'depart_at' => '2026-12-05 10:00', 'arrive_at' => '2026-12-05 09:00', 'duration_minutes' => 150,
            'stops' => 0, 'cabin_class' => 'economy', 'price' => 100, 'seats_available' => 5,
        ])->assertUnprocessable()->assertJsonValidationErrors('arrive_at');

        $id = Flight::first()->id;
        $this->deleteJson("/api/admin/flights/{$id}")->assertOk();
        $this->assertDatabaseMissing('flights', ['id' => $id]);
    }
}
