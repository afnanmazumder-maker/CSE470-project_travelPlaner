<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePreferencesRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PreferenceController extends Controller
{
    private const QUIZ_WEIGHT = 0.7;

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user()));
    }

    public function update(UpdatePreferencesRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data) {
            $user->profile()->updateOrCreate([], [
                'pace' => $data['pace'],
                'budget_band' => $data['budget_band'],
            ]);

            $user->interestWeights()->delete();
            $user->interestWeights()->createMany(
                collect($data['interests'])
                    ->unique()
                    ->map(fn ($tag) => ['tag' => $tag, 'weight' => self::QUIZ_WEIGHT])
                    ->values()
                    ->all()
            );
        });

        return response()->json($this->payload($user->fresh()));
    }

    private function payload(User $user): array
    {
        $profile = $user->profile;

        return [
            'onboarded' => $profile !== null,
            'pace' => $profile?->pace ?? 'moderate',
            'budget_band' => $profile?->budget_band ?? 'moderate',
            'interest_weights' => (object) $user->interestWeights()
                ->pluck('weight', 'tag')
                ->all(),
        ];
    }
}