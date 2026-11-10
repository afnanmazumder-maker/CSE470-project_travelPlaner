<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BudgetSuggestionRequest;
use App\Http\Requests\EstimateBudgetRequest;
use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Models\Budget;
use App\Models\Destination;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BudgetController extends Controller
{
    public function __construct(private BudgetService $budgets) {}

    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->budgets()
            ->with('destination:id,name,country')
            ->withSum('expenses', 'amount')
            ->latest()
            ->get()
            ->map(fn (Budget $budget): array => [
                'id' => $budget->id,
                'title' => $budget->title,
                'destination' => $budget->destination,
                'days' => $budget->days,
                'travelers' => $budget->travelers,
                'comfort' => $budget->comfort,
                'limit_amount' => $budget->limit_amount,
                'planned_total' => (float) array_sum($budget->planned),
                'spent_total' => (float) ($budget->expenses_sum_amount ?? 0),
            ]);

        return response()->json($items);
    }

    public function estimate(EstimateBudgetRequest $request): JsonResponse
    {
        $data = $request->validated();
        $destination = Destination::findOrFail($data['destination_id']);

        $planned = $this->budgets->estimate(
            $destination->cost_index, $data['days'], $data['travelers'], $data['comfort'],
        );

        return response()->json([
            'planned' => $planned,
            'total' => array_sum($planned),
        ]);
    }

    public function store(StoreBudgetRequest $request): JsonResponse
    {
        $data = $request->validated();
        $destination = Destination::findOrFail($data['destination_id']);

        $budget = $request->user()->budgets()->create($data + [
            'planned' => $this->budgets->estimate(
                $destination->cost_index, $data['days'], $data['travelers'], $data['comfort'],
            ),
        ]);

        return response()->json($this->budgets->summary($budget), 201);
    }

    public function show(Budget $budget): JsonResponse
    {
        Gate::authorize('manage', $budget);

        return response()->json($this->budgets->summary($budget));
    }

    public function update(UpdateBudgetRequest $request, Budget $budget): JsonResponse
    {
        Gate::authorize('manage', $budget);

        $budget->fill($request->validated());
        $destination = Destination::findOrFail($budget->destination_id);

        $budget->planned = $this->budgets->estimate(
            $destination->cost_index, $budget->days, $budget->travelers, $budget->comfort,
        );
        $budget->save();

        return response()->json($this->budgets->summary($budget));
    }

    public function destroy(Budget $budget): JsonResponse
    {
        Gate::authorize('manage', $budget);

        $budget->delete();

        return response()->json(['message' => 'Budget deleted']);
    }

    public function suggestions(BudgetSuggestionRequest $request, Budget $budget): JsonResponse
    {
        Gate::authorize('manage', $budget);

        $target = $request->validated('target') ?? $budget->limit_amount;

        if ($target === null) {
            throw ValidationException::withMessages([
                'target' => ['Set a spending limit or enter a target to get suggestions.'],
            ]);
        }

        $destination = Destination::findOrFail($budget->destination_id);
        $tierCosts = $this->budgets->tierCosts($destination->cost_index, $budget->days, $budget->travelers);

        return response()->json(
            $this->budgets->suggestSwaps($tierCosts, $budget->comfort, (float) $target)
        );
    }
}
