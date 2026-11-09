<?php

namespace Tests\Feature;

use App\Mail\TravelReminderMail;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReminderApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo('2026-12-04 20:00');

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        $this->booking(['type' => 'flight', 'title' => 'TG431 Bangkok → Bali', 'start_at' => '2026-12-05 09:30', 'end_at' => '2026-12-05 14:50', 'confirmation_number' => 'TG4XW9']);
        $this->booking(['type' => 'stay', 'title' => 'Siam Central Suites', 'start_at' => '2026-12-01 15:00', 'end_at' => '2026-12-05 11:00']);
        $this->booking(['type' => 'stay', 'title' => 'Ubud Rice Terrace Villa', 'start_at' => '2026-12-05 15:00', 'end_at' => '2026-12-09 11:00',
            'payment_due_at' => '2026-12-06 12:00', 'cancellation_deadline' => '2026-12-05 12:00', 'price' => 720]);
    }

    private function booking(array $attributes): Booking
    {
        $booking = new Booking($attributes + ['status' => 'confirmed']);
        $booking->user()->associate($this->user);
        $booking->save();

        return $booking;
    }

    /** @return list<string> */
    private function scanTypes(): array
    {
        $types = array_column($this->postJson('/api/reminders/run')->assertOk()->json('created'), 'type');
        sort($types);

        return $types;
    }

    public function test_every_due_reminder_is_created_and_emailed(): void
    {
        $this->assertSame(['cancellation_deadline', 'checkin_open', 'daily_digest', 'flight_24h', 'payment_due'], $this->scanTypes());

        Mail::assertSent(TravelReminderMail::class, 5);
        $this->assertStringContainsString('Flight in 14 hours', Notification::where('type', 'flight_24h')->value('title'));
        $this->assertStringContainsString('Free cancellation ends Sat 5 Dec', Notification::where('type', 'cancellation_deadline')->value('title'));
    }

    public function test_running_the_scan_again_sends_nothing_new(): void
    {
        $this->scanTypes();

        $this->assertSame([], $this->scanTypes());
        $this->assertSame(5, Notification::count());
    }

    public function test_the_digest_lists_todays_bookings_after_the_digest_time(): void
    {
        $this->travelTo('2026-12-05 07:30');

        $this->postJson('/api/reminders/run')->assertOk();

        $digest = Notification::where('type', 'daily_digest')->firstOrFail();
        $this->assertSame('Today: 4 things booked', $digest->title);
        $this->assertStringContainsString('09:30 — TG431 Bangkok → Bali', $digest->body);
        $this->assertStringContainsString('11:00 — Check out: Siam Central Suites', $digest->body);
    }

    public function test_no_digest_before_the_digest_time_and_no_reminders_for_cancelled_flights(): void
    {
        Booking::where('type', 'flight')->update(['status' => 'cancelled']);
        $this->travelTo('2026-12-04 06:30');

        $types = $this->scanTypes();

        $this->assertNotContains('daily_digest', $types);
        $this->assertNotContains('flight_24h', $types);
        $this->assertNotContains('checkin_open', $types);
    }

    public function test_turning_email_off_for_one_type_still_keeps_it_in_the_inbox(): void
    {
        $settings = $this->getJson('/api/notification-settings')->assertOk()->json('preferences');
        $settings['channels']['flight_24h']['email'] = false;
        $settings['channels']['payment_due']['in_app'] = false;

        $this->putJson('/api/notification-settings', $settings)->assertOk();
        $this->postJson('/api/reminders/run')->assertOk();

        Mail::assertSent(TravelReminderMail::class, 4);
        Mail::assertNotSent(TravelReminderMail::class, fn (TravelReminderMail $mail) => $mail->notification->type === 'flight_24h');

        $inbox = array_column($this->getJson('/api/notifications')->json('notifications'), 'type');
        $this->assertContains('flight_24h', $inbox);
        $this->assertNotContains('payment_due', $inbox);
    }

    public function test_unknown_notification_types_are_rejected(): void
    {
        $settings = $this->getJson('/api/notification-settings')->json('preferences');
        $settings['channels']['made_up'] = ['in_app' => true, 'email' => true];

        $this->putJson('/api/notification-settings', $settings)->assertUnprocessable()->assertJsonValidationErrors('channels');
    }

    public function test_the_scheduled_command_scans_every_traveler(): void
    {
        $this->artisan('reminders:dispatch')->expectsOutput('Sent 5 reminders.')->assertSuccessful();
    }
}
