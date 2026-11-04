<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchStaysRequest;
use App\Models\Accommodation;
use App\Services\StaySearchService;
use Illuminate\Http\JsonResponse;

/**
 * R9: stay search with filters and map search.
 */
class AccommodationController extends Controller
{
    public function __construct(private StaySearchService $stays) {}

    public function options(): JsonResponse
    {
        return response()->json([
            'amenities' => Accommodation::AMENITIES,
            'property_types' => Accommodation::PROPERTY_TYPES,
            'sorts' => StaySearchService::SORTS,
        ]);
    }

    public function search(SearchStaysRequest $request): JsonResponse
    {
        return response()->json($this->stays->search($request->validated()));
    }

    public function show(Accommodation $accommodation): JsonResponse
    {
        return response()->json($accommodation->load('destination:id,name,country'));
    }
}
