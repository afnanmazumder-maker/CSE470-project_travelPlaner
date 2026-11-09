<?php

namespace Tests\Feature;

use App\Events\FlightPriceDropped;
use App\Mail\TravelReminderMail;
use App\Models\Booking;
use App\Models\Destination;
use App\Models\Flight;
use App\Models\Notification;
use App\Models\PriceAlert;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\DestinationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_notification_routes_require_login(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_inbox_lists_in_app_notifications_with_the_unread_count(): void
    {
        $service = app(NotificationService::class);
        $service->notify($this->user, 'payment_due', 'Pay the hotel', 'Due tomorrow.');
        $service->notify($this->user, 'flight_24h', 'Flight soon', 'Tomorrow at 09:30.');

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'notifications');

        $this->postJson('/api/notifications/read-all')->assertOk()->assertJsonPath('marked', 2);
        $this->getJson('/api/notifications')->assertJsonPath('unread_count', 0);
    }

    public function test_a_notification_cannot_be_marked_read_by_someone_else(): void
    {
        $notification = app(NotificationService::class)->notify($this->user, 'payment_due', 'Pay the hotel', 'Due tomorrow.');

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/notifications/{$notification->id}/read")->assertNotFound();

        Sanctum::actingAs($this->user);
        $this->postJson("/api/notifications/{$notification->id}/read")->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_the_same_dedupe_key_is_only_sent_once(): void
    {
        $service = app(NotificationService::class);

        $this->assertNotNull($service->notify($this->user, 'payment_due', 'Pay', 'Due.', dedupeKey: 'payment_due:1'));
        $this->assertNull($service->notify($this->user, 'payment_due', 'Pay', 'Due.', dedupeKey: 'payment_due:1'));
        $this->assertSame(1, Notification::count());
    }

    public function test_a_price_drop_from_r9_becomes_a_notification_and_email(): void
    {
        $alert = new PriceAlert(['origin' => 'DAC', 'destination' => 'BKK', 'cabin_class' => 'economy', 'target_price' => 150]);
        $alert->user()->associate($this->user);
        $alert->save();

        FlightPriceDropped::dispatch($alert, 140.0, 200.0);

        $notification = Notification::where('type', 'price_drop')->firstOrFail();
        $this->assertSame('DAC → BKK fares dropped to $140.00', $notification->title);
        $this->assertStringContainsString('was $200.00, your target is $150.00', $notification->body);
        Mail::assertSent(TravelReminderMail::class, 1);
    }

    public function test_timeline_merges_flights_stays_and_deadlines_in_order(): void
    {
        $this->seed(DestinationSeeder::class);
        $trip = $this->user->budgets()->create([
            'title' => 'Bali trip', 'destination_id' => Destination::where('name', 'Bali')->value('id'),
            'days' => 5, 'travelers' => 1, 'comfort' => 'moderate', 'planned' => ['stay' => 500],
        ]);
        foreach ([
            ['type' => 'stay', 'title' => 'Ubud Rice Terrace Villa', 'start_at' => '2026-12-05 15:00', 'end_at' => '2026-12-09 11:00', 'cancellation_deadline' => '2026-12-05 12:00'],
            ['type' => 'flight', 'title' => 'TG431 Bangkok → Bali', 'start_at' => '2026-12-05 09:30', 'end_at' => '2026-12-05 14:50'],
            ['type' => 'flight', 'title' => 'Cancelled flight', 'start_at' => '2026-12-05 08:00', 'status' => 'cancelled'],
        ] as $attributes) {
            $booking = new Booking($attributes + ['status' => 'confirmed', 'budget_id' => $trip->id]);
            $booking->user()->associate($this->user);
            $booking->save();
        }

        $days = $this->getJson("/api/budgets/{$trip->id}/timeline")->assertOk()->json('days');

        $this->assertSame(['2026-12-05', '2026-12-09'], array_column($days, 'date'));
        $this->assertSame(['flight', 'deadline', 'stay'], array_column($days[0]['events'], 'kind'));
        $this->assertSame('Check out: Ubud Rice Terrace Villa', $days[1]['events'][0]['title']);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/budgets/{$trip->id}/timeline")->assertForbidden();
        $this->getJson('/api/timeline')->assertOk()->assertJsonCount(0, 'days');
    }

    public function test_admin_fare_change_reaches_the_traveler_end_to_end(): void
    {
        $flight = Flight::create([
            'airline' => 'Test Air', 'airline_code' => 'TA', 'flight_number' => 'TA1', 'origin' => 'DAC', 'destination' => 'BKK',
            'depart_at' => '2026-12-05 10:00', 'arrive_at' => '2026-12-05 13:30', 'duration_minutes' => 150, 'stops' => 0,
            'cabin_class' => 'economy', 'price' => 200, 'seats_available' => 9,
        ]);
        $this->postJson('/api/price-alerts', ['origin' => 'DAC', 'destination' => 'BKK', 'depart_date' => '2026-12-05', 'cabin_class' => 'economy'])->assertCreated();

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        Sanctum::actingAs($admin);
        $this->putJson("/api/admin/flights/{$flight->id}", array_merge($flight->only(['airline', 'airline_code', 'flight_number', 'origin', 'destination', 'duration_minutes', 'stops', 'cabin_class', 'seats_available']), [
            'depart_at' => '2026-12-05 10:00', 'arrive_at' => '2026-12-05 13:30', 'price' => 170,
        ]))->assertOk();

        Sanctum::actingAs($this->user);
        $this->getJson('/api/notifications')->assertJsonPath('notifications.0.type', 'price_drop');
    }
}
