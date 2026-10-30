<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FeedbackRequest;
use App\Http\Requests\RecommendationRequest;
use App\Models\Attraction;
use App\Models\InterestWeight;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;

class RecommendationController extends Controller
{
    public function index(RecommendationRequest $request, AiService $ai): JsonResponse
    {
        $data = $request->validated();
        $limit = $data['limit'] ?? 10;

        $attractions = Attraction::with('destination:id,name,country')
            ->when(
                isset($data['destination_id']),
                fn ($query) => $query->where('destination_id', $data['destination_id'])
            )
            ->get();

        $weights = $request->user()->interestWeights()->pluck('weight', 'tag')->all();

        $payload = $attractions->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'tags' => $a->tags,
            'popularity' => $a->popularity,
        ])->values()->all();

        $result = $ai->recommend($weights, $payload, $limit);
        $available = $result !== null;

        if (! $available) {
            $result = [
                'fallback_used' => true,
                'recommendations' => $attractions
                    ->sortByDesc('popularity')
                    ->take($limit)
                    ->map(fn ($a) => [
                        'attraction_id' => $a->id,
                        'name' => $a->name,
                        'score' => $a->popularity,
                        'reason' => 'Popular with travelers',
                    ])
                    ->values()
                    ->all(),
            ];
        }

        $byId = $attractions->keyBy('id');

        $recommendations = collect($result['recommendations'])->map(function ($r) use ($byId) {
            $attraction = $byId[$r['attraction_id']];
            $r['tags'] = $attraction->tags;
            $r['destination'] = $attraction->destination;

            return $r;
        })->values()->all();

        return response()->json([
            'recommendations' => $recommendations,
            'fallback_used' => $result['fallback_used'],
            'ai_service_available' => $available,
        ]);
    }

    public function feedback(FeedbackRequest $request, Attraction $attraction, AiService $ai): JsonResponse
    {
        $user = $request->user();

        $weights = $user->interestWeights()->pluck('weight', 'tag')->all();
        $updated = $ai->updateWeights($weights, $attraction->tags, $request->validated()['action']);

        if ($updated === null) {
            return response()->json([
                'message' => 'The recommendation service is unavailable. Please try again later.',
            ], 503);
        }

        $rows = collect($updated['interest_weights'])
            ->map(fn ($weight, $tag) => ['user_id' => $user->id, 'tag' => $tag, 'weight' => $weight])
            ->values()
            ->all();

        InterestWeight::upsert($rows, ['user_id', 'tag'], ['weight']);

        return response()->json(['interest_weights' => (object) $updated['interest_weights']]);
    }
}