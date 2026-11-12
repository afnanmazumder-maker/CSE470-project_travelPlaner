<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Services\ItineraryBoardService;
use App\Services\TripMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * R16: the shared itinerary and the trip's activity history.
 * View: ItineraryBoard.
 */
class ItineraryBoardController extends Controller
{
    public function __construct(
        private ItineraryBoardService $board,
        private TripMemberService $members,
    ) {}

    public function show(Request $request, Budget $budget): JsonResponse
    {
        Gate::allowIf($this->members->canView($budget, $request->user()));

        return response()->json($this->board->board($budget, $request->user()));
    }

    public function activity(Request $request, Budget $budget): JsonResponse
    {
        Gate::allowIf($this->members->canView($budget, $request->user()));

        return response()->json($this->board->activity($budget));
    }
}
