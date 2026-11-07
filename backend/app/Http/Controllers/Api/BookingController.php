<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Budget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * R9: saved flight and stay bookings with confirmation number, dates, status
 * and deadlines. A booking can belong to a trip (an R7 budget).
 */
class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'budget_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::in(Booking::TYPES)],
            'status' => ['nullable', Rule::in(Booking::STATUSES)],
        ]);

        $bookings = Booking::where('user_id', $request->user()->id)
            ->with('budget:id,title')
            ->when(! empty($filters['budget_id']), fn ($query) => $query->where('budget_id', $filters['budget_id']))
            ->when(! empty($filters['type']), fn ($query) => $query->where('type', $filters['type']))
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('start_at')
            ->get();

        return response()->json($bookings);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->authorizeBudget($data['budget_id'] ?? null);

        $booking = new Booking($data);
        $booking->user()->associate($request->user());
        $booking->save();

        return response()->json($booking->load('budget:id,title'), 201);
    }

    public function show(Booking $booking): JsonResponse
    {
        Gate::authorize('manage', $booking);

        return response()->json($booking->load(['budget:id,title', 'flight', 'accommodation']));
    }

    public function update(StoreBookingRequest $request, Booking $booking): JsonResponse
    {
        Gate::authorize('manage', $booking);
        $data = $request->validated();
        $this->authorizeBudget($data['budget_id'] ?? null);

        $booking->update($data);

        return response()->json($booking->load('budget:id,title'));
    }

    public function destroy(Booking $booking): JsonResponse
    {
        Gate::authorize('manage', $booking);

        $booking->delete();

        return response()->json(['message' => 'Booking deleted']);
    }

    private function authorizeBudget(?int $budgetId): void
    {
        if ($budgetId !== null) {
            Gate::authorize('manage', Budget::findOrFail($budgetId));
        }
    }
}
