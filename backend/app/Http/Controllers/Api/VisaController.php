<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckVisaRequest;
use App\Http\Requests\UpdatePassportRequest;
use App\Models\Budget;
use App\Models\Country;
use App\Services\VisaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * R6: visa and entry requirement checker for travelers.
 * Checking is public, like Explore; the saved passport and trip warning need a login.
 */
class VisaController extends Controller
{
    public function __construct(private VisaService $visas) {}

    public function countries(): JsonResponse
    {
        return response()->json([
            'countries' => Country::orderBy('name')->get(['code', 'name']),
            'default_passport' => config('visa.default_passport'),
            'dataset_updated_on' => config('visa.dataset.updated_on'),
        ]);
    }

    public function check(CheckVisaRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json(
            $this->visas->check($data['passport'], $data['destination'], $data['transit'] ?? null)
        );
    }

    public function passport(Request $request): JsonResponse
    {
        return response()->json(['passport_country' => $request->user()->passport_country]);
    }

    public function updatePassport(UpdatePassportRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['passport_country' => $request->validated('passport_country')])->save();

        return response()->json(['passport_country' => $user->passport_country]);
    }

    public function budget(Request $request, Budget $budget): JsonResponse
    {
        Gate::authorize('manage', $budget);

        return response()->json($this->visas->forBudget($budget, $request->user()));
    }
}
