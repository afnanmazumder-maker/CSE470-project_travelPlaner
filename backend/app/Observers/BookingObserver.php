<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\User;
use App\Services\TripActivityService;
use Illuminate\Support\Facades\Auth;

/**
 * R16: writes the trip history when an itinerary item (a booking saved to a
 * trip) is added, changed or removed, and updates open boards.
 */
class BookingObserver
{
    public function __construct(private TripActivityService $activity) {}

    public function created(Booking $booking): void
    {
        $this->record($booking, $booking->budget_id, 'item.added', "added the {$booking->type} \"{$booking->title}\"");
    }

    public function updated(Booking $booking): void
    {
        if ($booking->wasChanged('budget_id') && $booking->getOriginal('budget_id') !== null) {
            $this->record($booking, $booking->getOriginal('budget_id'), 'item.removed', "moved \"{$booking->title}\" to another trip");
        }

        $description = $booking->wasChanged('status')
            ? "marked \"{$booking->title}\" as {$booking->status}"
            : "updated \"{$booking->title}\"";

        $this->record($booking, $booking->budget_id, 'item.updated', $description);
    }

    public function deleted(Booking $booking): void
    {
        $this->record($booking, $booking->budget_id, 'item.removed', "removed \"{$booking->title}\"");
    }

    private function record(Booking $booking, ?int $budgetId, string $action, string $description): void
    {
        $user = Auth::user();

        if ($budgetId === null || ! $user instanceof User) {
            return;
        }

        $this->activity->record($budgetId, $user, $action, $description, ['booking_id' => $booking->id]);
    }
}
