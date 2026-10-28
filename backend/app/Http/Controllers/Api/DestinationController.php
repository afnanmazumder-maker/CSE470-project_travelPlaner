<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DestinationIndexRequest;
use App\Models\Destination;
use Illuminate\Http\JsonResponse;

class DestinationController extends Controller
{
    public function index(DestinationIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $query = Destination::query()->withCount('attractions');

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', $term)
                    ->orWhere('country', 'ilike', $term)
                    ->orWhere('region', 'ilike', $term);
            });
        }

        foreach (['region', 'climate', 'budget_level'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['theme'])) {
            $query->whereJsonContains('themes', $filters['theme']);
        }

        if (! empty($filters['month'])) {
            $query->whereJsonContains('best_months', (int) $filters['month']);
        }

        if (! empty($filters['min_safety'])) {
            $query->where('safety_rating', '>=', $filters['min_safety']);
        }

        return response()->json(
            $query->orderBy('name')->paginate($filters['per_page'] ?? 12)
        );
    }

    public function show(Destination $destination): JsonResponse
    {
        $destination->load('attractions');

        return response()->json($destination);
    }
}