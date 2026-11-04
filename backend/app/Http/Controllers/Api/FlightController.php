<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MultiCityFlightsRequest;
use App\Http\Requests\PriceCalendarRequest;
use App\Http\Requests\SearchFlightsRequest;
use App\Http\Requests\StoreFlightRequest;
use App\Http\Requests\StorePriceAlertRequest;
use App\Models\Flight;
use App\Models\PriceAlert;
use App\Services\FlightSearchService;
use App\Services\PriceAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * R9: flight search, multi-city, price calendar, price-drop alerts, and the
 * admin endpoints behind the flight admin page.
 */
class FlightController extends Controller
{
    public function __construct(
        private FlightSearchService $search,
        private PriceAlertService $alerts,
    ) {}

    public function options(): JsonResponse
    {
        return response()->json([
            'airports' => collect(config('flights.airports'))->map(fn (array $airport, string $code): array => ['code' => $code] + $airport)->values(),
            'cabin_classes' => config('flights.cabin_classes'),
            'depart_windows' => Flight::DEPART_WINDOWS,
            'sorts' => FlightSearchService::SORTS,
        ]);
    }

    public function search(SearchFlightsRequest $request): JsonResponse
    {
        $results = $this->search->search($request->validated());

        return response()->json(['count' => $results->count(), 'results' => $results]);
    }

    public function multiCity(MultiCityFlightsRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->search->multiCity($data['legs'], $data['passengers'], $data['cabin_class'], $data['sort'] ?? 'price'));
    }

    public function priceCalendar(PriceCalendarRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->search->priceCalendar(
            $data['origin'], $data['destination'], $data['cabin_class'],
            Carbon::createFromFormat('Y-m-d', $data['month'].'-01'), $data['passengers'] ?? 1,
        ));
    }

    public function alerts(Request $request): JsonResponse
    {
        return response()->json(PriceAlert::where('user_id', $request->user()->id)->latest()->get());
    }

    public function storeAlert(StorePriceAlertRequest $request): JsonResponse
    {
        $alert = new PriceAlert($request->validated());
        $alert->user()->associate($request->user());
        $alert->save();

        // The first check records today's fare, and announces at once if the target is already met.
        $announced = $this->alerts->check(collect([$alert]));

        return response()->json(['alert' => $alert->fresh(), 'announced' => $announced], 201);
    }

    public function destroyAlert(Request $request, PriceAlert $priceAlert): JsonResponse
    {
        abort_unless((int) $priceAlert->user_id === (int) $request->user()->id, 404);

        $priceAlert->delete();

        return response()->json(['message' => 'Price alert deleted']);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $request->validate([
            'origin' => ['nullable', 'string', 'size:3'],
            'destination' => ['nullable', 'string', 'size:3'],
            'date' => ['nullable', 'date'],
        ]);

        $flights = Flight::query()
            ->when($request->filled('origin'), fn ($query) => $query->where('origin', strtoupper($request->input('origin'))))
            ->when($request->filled('destination'), fn ($query) => $query->where('destination', strtoupper($request->input('destination'))))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('depart_at', $request->input('date')))
            ->orderBy('depart_at')
            ->paginate(25);

        return response()->json($flights);
    }

    public function adminStore(StoreFlightRequest $request): JsonResponse
    {
        $flight = Flight::create($request->validated());

        return response()->json([
            'flight' => $flight,
            'price_alerts_announced' => $this->alerts->checkRoute($flight->origin, $flight->destination, $flight->cabin_class),
        ], 201);
    }

    public function adminUpdate(StoreFlightRequest $request, Flight $flight): JsonResponse
    {
        $flight->update($request->validated());

        return response()->json([
            'flight' => $flight,
            'price_alerts_announced' => $this->alerts->checkRoute($flight->origin, $flight->destination, $flight->cabin_class),
        ]);
    }

    public function adminDestroy(Flight $flight): JsonResponse
    {
        $flight->delete();

        return response()->json(['message' => 'Flight deleted']);
    }
}
