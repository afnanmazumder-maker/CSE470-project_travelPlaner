<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBudgetTrackingRequest;
use App\Models\Budget;
use App\Models\BudgetTracking;
use App\Models\ExchangeRate;
use App\Services\DeviationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * R8: planned vs actual per category and day, the alert threshold, and the
 * end-of-trip variance report. Expenses themselves go through ExpenseController.
 */
class SpendTrackerController extends Controller
{
    public function __construct(private DeviationService $deviation) {}

    public function currencies(): JsonResponse
    {
        return response()->json([
            'currencies' => config('currency.currencies'),
            'home' => config('currency.home'),
            'latest_rate_date' => ExchangeRate::where('source', 'frankfurter')->max('rate_date'),
        ]);
    }

    public function show(Budget $budget): JsonResponse
    {
        Gate::authorize('manage', $budget);

        return response()->json($this->deviation->summary($budget));
    }

    public function updateTracking(UpdateBudgetTrackingRequest $request, Budget $budget): JsonResponse
    {
        Gate::authorize('manage', $budget);

        BudgetTracking::forBudget($budget)->update($request->validated());
        $alerts = $this->deviation->checkThresholds($budget);

        return response()->json(['summary' => $this->deviation->summary($budget), 'alerts' => $alerts]);
    }

    public function report(Budget $budget): JsonResponse
    {
        Gate::authorize('manage', $budget);

        return response()->json($this->deviation->report($budget));
    }

    public function reportCsv(Budget $budget): Response
    {
        Gate::authorize('manage', $budget);

        return response($this->deviation->toCsv($this->deviation->report($budget)), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="variance-report-'.Str::slug($budget->title).'.csv"',
        ]);
    }
}
