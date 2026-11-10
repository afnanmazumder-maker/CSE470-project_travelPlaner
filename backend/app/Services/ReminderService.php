<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * R16: the scheduled reminder scan (reminders:dispatch). Every reminder has a
 * dedupe key, so it is sent once however often the scan runs.
 */
class ReminderService
{
    public function __construct(
        private NotificationService $notifications,
        private TimelineService $timeline,
    ) {}

    /**
     * @return Collection<int, Notification> the notifications created
     */
    public function run(?User $only = null, ?CarbonInterface $now = null): Collection
    {
        $now = $now ? Carbon::instance($now) : now();
        $users = $only ? collect([$only]) : User::whereIn('id', Booking::query()->select('user_id'))->lazy();
        $created = collect();

        foreach ($users as $user) {
            $preference = NotificationPreference::forUser($user);
            $bookings = Booking::where('user_id', $user->id)->where('status', '!=', 'cancelled')->get();

            $created->push(
                ...$this->flightReminders($user, $preference, $bookings, $now),
                ...$this->bookingDeadlines($user, $preference, $bookings, $now),
                ...$this->dailyDigest($user, $preference, $bookings, $now),
            );
        }

        return $created->filter()->values();
    }

    /**
     * Check-in opening and flights leaving within 24 hours.
     *
     * @param  Collection<int, Booking>  $bookings
     * @return list<Notification|null>
     */
    private function flightReminders(User $user, NotificationPreference $preference, Collection $bookings, Carbon $now): array
    {
        $created = [];
        $flights = $bookings->where('type', 'flight')
            ->filter(fn (Booking $b): bool => $b->start_at->between($now, $now->copy()->addHours($preference->checkin_lead_hours)));

        foreach ($flights as $flight) {
            $departs = $flight->start_at->format('D j M, H:i');

            $created[] = $this->notifications->notify(
                $user, 'checkin_open',
                "Check-in is open: {$flight->title}",
                "Online check-in usually opens {$preference->checkin_lead_hours} hours before departure. Your flight leaves {$departs}"
                    .($flight->confirmation_number ? " (confirmation {$flight->confirmation_number})." : '.'),
                ['booking_id' => $flight->id], $flight->budget, "checkin_open:{$flight->id}",
            );

            if ($flight->start_at->lte($now->copy()->addDay())) {
                $hours = max(1, (int) round($now->diffInMinutes($flight->start_at) / 60));
                $created[] = $this->notifications->notify(
                    $user, 'flight_24h',
                    "Flight in {$hours} hours: {$flight->title}",
                    "{$flight->title} leaves {$departs}. Leave time for the airport and keep your passport and boarding pass ready.",
                    ['booking_id' => $flight->id], $flight->budget, "flight_24h:{$flight->id}",
                );
            }
        }

        return $created;
    }

    /**
     * Payments due and free-cancellation deadlines.
     *
     * @param  Collection<int, Booking>  $bookings
     * @return list<Notification|null>
     */
    private function bookingDeadlines(User $user, NotificationPreference $preference, Collection $bookings, Carbon $now): array
    {
        $until = $now->copy()->addDays($preference->deadline_lead_days);
        $created = [];

        foreach ($bookings->filter(fn (Booking $b): bool => $b->payment_due_at?->between($now, $until) ?? false) as $booking) {
            $amount = $booking->price ? ' of $'.number_format($booking->price, 2) : '';
            $created[] = $this->notifications->notify(
                $user, 'payment_due',
                "Payment due {$booking->payment_due_at->format('D j M')}: {$booking->title}",
                "A payment{$amount} for {$booking->title} is due by {$booking->payment_due_at->format('D j M, H:i')}.",
                ['booking_id' => $booking->id], $booking->budget,
                "payment_due:{$booking->id}:{$booking->payment_due_at->toDateString()}",
            );
        }

        foreach ($bookings->filter(fn (Booking $b): bool => $b->cancellation_deadline?->between($now, $until) ?? false) as $booking) {
            $created[] = $this->notifications->notify(
                $user, 'cancellation_deadline',
                "Free cancellation ends {$booking->cancellation_deadline->format('D j M')}: {$booking->title}",
                "After {$booking->cancellation_deadline->format('D j M, H:i')} you may not get a refund for {$booking->title}. Cancel or confirm before then.",
                ['booking_id' => $booking->id], $booking->budget,
                "cancellation_deadline:{$booking->id}:{$booking->cancellation_deadline->toDateString()}",
            );
        }

        return $created;
    }

    /**
     * One digest per travel day (a flight that day, or a night in a booked stay),
     * sent after the user's digest time.
     *
     * @param  Collection<int, Booking>  $bookings
     * @return list<Notification|null>
     */
    private function dailyDigest(User $user, NotificationPreference $preference, Collection $bookings, Carbon $now): array
    {
        if (! $preference->daily_digest || $now->format('H:i') < $preference->digest_time) {
            return [];
        }

        $today = $now->copy()->startOfDay();
        $travelling = $bookings->contains(fn (Booking $b): bool => $b->type === 'flight'
            ? $b->start_at->isSameDay($today)
            : $b->start_at->copy()->startOfDay()->lte($today) && ($b->end_at ?? $b->start_at)->copy()->startOfDay()->gte($today));

        if (! $travelling) {
            return [];
        }

        $events = $this->timeline->onDate($bookings, $today);
        $lines = $events->map(fn (array $event): string => substr($event['starts_at'], 11, 5).' — '.$event['title'])->all();

        return [$this->notifications->notify(
            $user, 'daily_digest',
            'Today: '.($events->isEmpty() ? 'a free travel day' : $events->count().' things booked'),
            $events->isEmpty() ? 'Nothing is booked for today. Enjoy exploring!' : implode("\n", $lines),
            ['date' => $today->toDateString(), 'events' => $events->count()], null,
            "daily_digest:{$today->toDateString()}",
        )];
    }
}
