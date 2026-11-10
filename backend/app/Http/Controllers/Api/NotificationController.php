<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationSettingsRequest;
use App\Models\Booking;
use App\Models\Budget;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\PriceAlert;
use App\Services\PriceAlertService;
use App\Services\ReminderService;
use App\Services\TimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * R16: in-app notifications, notification settings, the trip timeline and a
 * "run the reminder scan now" action.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Notification::where('user_id', $request->user()->id)->where('show_in_app', true);

        return response()->json([
            'unread_count' => (clone $query)->whereNull('read_at')->count(),
            'notifications' => $query->with('budget:id,title')->latest()->limit(100)->get(),
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 404);

        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->json($notification);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $marked = Notification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['marked' => $marked]);
    }

    public function settings(Request $request): JsonResponse
    {
        $preference = NotificationPreference::forUser($request->user());

        return response()->json([
            'preferences' => array_merge($preference->toArray(), ['channels' => $preference->allChannels()]),
            'types' => collect(Notification::TYPES)->map(fn (array $type, string $key): array => ['key' => $key, 'label' => $type['label']])->values(),
        ]);
    }

    public function updateSettings(UpdateNotificationSettingsRequest $request): JsonResponse
    {
        $preference = NotificationPreference::forUser($request->user());
        $preference->update($request->validated());

        return response()->json($preference);
    }

    /** Every upcoming booking of the user, as one timeline. */
    public function timeline(Request $request, TimelineService $timeline): JsonResponse
    {
        $bookings = Booking::where('user_id', $request->user()->id)->orderBy('start_at')->get();

        return response()->json(['budget' => null, 'days' => $timeline->group($bookings)]);
    }

    /** The timeline of one trip (an R7 budget). */
    public function budgetTimeline(Budget $budget, TimelineService $timeline): JsonResponse
    {
        Gate::authorize('manage', $budget);

        $bookings = Booking::where('budget_id', $budget->id)->orderBy('start_at')->get();

        return response()->json(['budget' => $budget->only(['id', 'title', 'days']), 'days' => $timeline->group($bookings)]);
    }

    /** Runs the scheduled scan now for this user (the scheduler runs it every 15 minutes). */
    public function runReminders(Request $request, ReminderService $reminders, PriceAlertService $priceAlerts): JsonResponse
    {
        $user = $request->user();
        $created = $reminders->run($user);
        $prices = $priceAlerts->check(PriceAlert::where('user_id', $user->id)->where('is_active', true)->get());

        return response()->json([
            'created' => $created->map->only(['id', 'type', 'title']),
            'price_drops' => $prices,
        ]);
    }
}
