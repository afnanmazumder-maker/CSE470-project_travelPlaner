<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Budget;
use App\Models\Expense;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ExpenseController extends Controller
{
    public function __construct(private BudgetService $budgets) {}

    public function store(StoreExpenseRequest $request, Budget $budget): JsonResponse
    {
        Gate::authorize('manage', $budget);

        $data = $request->validated();

        $expense = $budget->expenses()->create($data + ['spent_on' => now()->toDateString()]);

        return response()->json([
            'expense' => $expense,
            'budget' => $this->budgets->summary($budget),
        ], 201);
    }

    public function destroy(Budget $budget, Expense $expense): JsonResponse
    {
        Gate::authorize('manage', $budget);
        abort_unless($expense->budget_id === $budget->id, 404);

        $expense->delete();

        return response()->json($this->budgets->summary($budget));
    }
}
