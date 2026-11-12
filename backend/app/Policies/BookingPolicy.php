<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Services\TripMemberService;

class BookingPolicy
{
    public function __construct(private TripMemberService $trips) {}

    /**
     * The person who saved the booking, or (R16) an owner or editor of the group trip it belongs to.
     */
    public function manage(User $user, Booking $booking): bool
    {
        if ((int) $booking->user_id === (int) $user->id) {
            return true;
        }

        return $booking->budget !== null && $this->trips->canEdit($booking->budget, $user);
    }
}
