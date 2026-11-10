<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Budget;
use App\Models\Expense;
use App\Services\BudgetService;
use App\Services\CurrencyService;
use App\Services\DeviationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ExpenseController extends Controller
{
    public function __construct(
        private BudgetService $budgets,
        private CurrencyService $currency,
        private DeviationService $deviation,
    ) {}

    public function store(StoreExpenseRequest $request, Budget $budget): JsonResponse
    {
        Gate::authorize('manage', $budget);

        $data = $request->validated();
        $spentOn = Carbon::parse($data['spent_on'] ?? now())->startOfDay();
        $currency = $data['currency'] ?? config('currency.home');

        // R8: convert with the rate for the day the money was spent; amount stays in USD.
        $conversion = $this->currency->convert((float) $data['amount'], $currency, config('currency.home'), $spentOn);

        $expense = $budget->expenses()->create([
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'amount' => $conversion['converted'],
            'currency' => $currency,
            'original_amount' => $data['amount'],
            'rate_used' => $conversion['rate'],
            'rate_date' => $conversion['rate_date'],
            'rate_source' => $conversion['source'],
            'spent_on' => $spentOn->toDateString(),
        ]);

        return response()->json([
            'expense' => $expense,
            'budget' => $this->budgets->summary($budget),
            'deviation_alerts' => $this->deviation->checkThresholds($budget),
        ], 201);
    }

    public function destroy(Budget $budget, Expense $expense): JsonResponse
    {
        Gate::authorize('manage', $budget);
        abort_unless($expense->budget_id === $budget->id, 404);

        $expense->delete();
        $this->deviation->checkThresholds($budget);

        return response()->json($this->budgets->summary($budget));
    }
}
