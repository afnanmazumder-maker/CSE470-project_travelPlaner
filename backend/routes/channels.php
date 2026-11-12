<?php

use App\Models\Budget;
use App\Models\User;
use App\Services\TripMemberService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// R16: live updates of a group trip's board, for everyone on the trip.
Broadcast::channel('trips.{budget}', function (User $user, Budget $budget) {
    return app(TripMemberService::class)->canView($budget, $user);
});
