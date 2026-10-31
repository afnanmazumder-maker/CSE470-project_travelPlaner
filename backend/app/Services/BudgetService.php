<?php

namespace App\Services;

use App\Models\Budget;

class BudgetService
{
    /** @var list<string> */
    public const CATEGORIES = [
        'flights', 'stay', 'food', 'transport', 'activities',
        'visa', 'insurance', 'shopping', 'emergency',
    ];

    /** @var list<string> Ordered from cheapest to most comfortable. */
    private const TIERS = ['budget', 'moderate', 'luxury'];

    private const TIER_MULTIPLIER = ['budget' => 0.6, 'moderate' => 1.0, 'luxury' => 1.8];

    private const TIER_VALUE = ['budget' => 1, 'moderate' => 2, 'luxury' => 3];

    /** How much a traveler cares about keeping a category comfortable. */
    private const CATEGORY_WEIGHT = [
        'flights' => 3, 'stay' => 3, 'food' => 2, 'activities' => 2,
        'transport' => 1, 'shopping' => 1, 'visa' => 1, 'insurance' => 1,
    ];

    /** Per person, per day, in USD at a cost index of 100 and moderate comfort. */
    private const DAILY_RATE = ['food' => 90, 'transport' => 40, 'activities' => 60, 'shopping' => 30];

    private const STAY_ROOM_RATE = 150;

    private const VISA_PER_PERSON = 50;

    private const INSURANCE_PER_PERSON_DAY = 4;

    private const EMERGENCY_SHARE = 0.10;

    /**
     * Cost of every category at every comfort tier, in whole USD.
     * The emergency buffer is left out because it depends on the other totals.
     *
     * @return array<string, array<string, int>>
     */
    public function tierCosts(int $costIndex, int $days, int $travelers): array
    {
        $factor = $costIndex / 100;
        $rooms = (int) ceil($travelers / 2);
        $costs = [];

        foreach (self::TIERS as $tier) {
            $multiplier = self::TIER_MULTIPLIER[$tier];

            $costs['flights'][$tier] = (int) round($travelers * (350 + 6 * $costIndex) * $multiplier);
            $costs['stay'][$tier] = (int) round($rooms * $days * self::STAY_ROOM_RATE * $factor * $multiplier);

            foreach (self::DAILY_RATE as $category => $rate) {
                $costs[$category][$tier] = (int) round($travelers * $days * $rate * $factor * $multiplier);
            }

            $costs['visa'][$tier] = $travelers * self::VISA_PER_PERSON;
            $costs['insurance'][$tier] = $travelers * $days * self::INSURANCE_PER_PERSON_DAY;
        }

        return $costs;
    }

    /**
     * Planned cost per category for a comfort level, in whole USD.
     *
     * @return array<string, int>
     */
    public function estimate(int $costIndex, int $days, int $travelers, string $comfort): array
    {
        $tierCosts = $this->tierCosts($costIndex, $days, $travelers);
        $planned = [];

        foreach (self::CATEGORIES as $category) {
            if ($category !== 'emergency') {
                $planned[$category] = $tierCosts[$category][$comfort];
            }
        }

        $planned['emergency'] = $this->emergencyBuffer(array_sum($planned));

        return $planned;
    }

    /**
     * Knapsack optimizer: pick one comfort tier per category so the total fits
     * under the limit while keeping as much comfort as possible. Only swaps to
     * cheaper tiers are considered.
     *
     * @param  array<string, array<string, int>>  $tierCosts
     * @return array{achievable: bool, limit: float, current_total: int, new_total: int, savings: int, swaps: list<array{category: string, from: string, to: string, saves: int}>}
     */
    public function suggestSwaps(array $tierCosts, string $comfort, float $limit): array
    {
        $allowedTiers = array_slice(self::TIERS, 0, array_search($comfort, self::TIERS, true) + 1);
        $categories = array_keys($tierCosts);
        $options = $this->swapOptions($tierCosts, $categories, $allowedTiers, $comfort);

        $capacity = $this->subtotalCapacity($limit);
        $capacity = min($capacity, $this->sumOfExpensiveOptions($options));

        $chosen = $capacity >= 0 ? $this->solveKnapsack($options, $capacity) : null;
        $achievable = $chosen !== null;

        if (! $achievable) {
            $chosen = array_map(
                fn (array $categoryOptions): int => $this->cheapestOptionIndex($categoryOptions),
                $options,
            );
        }

        $plan = [];
        foreach ($options as $index => $categoryOptions) {
            $plan[$categories[$index]] = $categoryOptions[$chosen[$index]];
        }

        $currentSubtotal = 0;
        $newSubtotal = 0;
        $swaps = [];

        foreach ($categories as $category) {
            $currentCost = $tierCosts[$category][$comfort];
            $currentSubtotal += $currentCost;
            $newSubtotal += $plan[$category]['cost'];

            if ($plan[$category]['tier'] !== $comfort) {
                $swaps[] = [
                    'category' => $category,
                    'from' => $comfort,
                    'to' => $plan[$category]['tier'],
                    'saves' => $currentCost - $plan[$category]['cost'],
                ];
            }
        }

        $currentTotal = $currentSubtotal + $this->emergencyBuffer($currentSubtotal);
        $newTotal = $newSubtotal + $this->emergencyBuffer($newSubtotal);

        return [
            'achievable' => $achievable,
            'limit' => $limit,
            'current_total' => $currentTotal,
            'new_total' => $newTotal,
            'savings' => $currentTotal - $newTotal,
            'swaps' => $swaps,
        ];
    }

    /**
     * Planned vs actual spending, progress per category and over-budget alerts.
     *
     * @return array<string, mixed>
     */
    public function summary(Budget $budget): array
    {
        $budget->load([
            'destination:id,name,country,cost_index',
            'expenses' => fn ($query) => $query->orderByDesc('spent_on')->orderByDesc('id'),
        ]);

        $spentByCategory = array_fill_keys(self::CATEGORIES, 0.0);
        foreach ($budget->expenses as $expense) {
            $spentByCategory[$expense->category] += $expense->amount;
        }

        $categories = [];
        $alerts = [];

        foreach (self::CATEGORIES as $category) {
            $planned = (float) ($budget->planned[$category] ?? 0);
            $spent = round($spentByCategory[$category], 2);
            $label = ucfirst($category);

            $categories[] = [
                'category' => $category,
                'planned' => $planned,
                'spent' => $spent,
                'percent' => $planned > 0 ? (int) round($spent / $planned * 100) : ($spent > 0 ? 100 : 0),
            ];

            if ($spent > $planned) {
                $alerts[] = [
                    'level' => 'danger',
                    'category' => $category,
                    'message' => "{$label} is over budget by {$this->money($spent - $planned)}.",
                ];
            } elseif ($planned > 0 && $spent / $planned >= 0.9) {
                $alerts[] = [
                    'level' => 'warning',
                    'category' => $category,
                    'message' => "{$label} has used ".(int) round($spent / $planned * 100).'% of its plan.',
                ];
            }
        }

        $plannedTotal = (float) array_sum($budget->planned);
        $spentTotal = round(array_sum($spentByCategory), 2);
        $limit = $budget->limit_amount;

        if ($limit !== null) {
            if ($spentTotal > $limit) {
                $alerts[] = [
                    'level' => 'danger',
                    'category' => null,
                    'message' => "You are over your spending limit by {$this->money($spentTotal - $limit)}.",
                ];
            } elseif ($spentTotal >= $limit * 0.9) {
                $alerts[] = [
                    'level' => 'warning',
                    'category' => null,
                    'message' => 'You have used '.(int) round($spentTotal / $limit * 100).'% of your spending limit.',
                ];
            }

            if ($plannedTotal > $limit) {
                $alerts[] = [
                    'level' => 'warning',
                    'category' => null,
                    'message' => "Your plan ({$this->money($plannedTotal)}) is above your limit ({$this->money($limit)}). Try the cheaper swaps.",
                ];
            }
        }

        return [
            'id' => $budget->id,
            'title' => $budget->title,
            'destination' => $budget->destination,
            'days' => $budget->days,
            'travelers' => $budget->travelers,
            'comfort' => $budget->comfort,
            'limit_amount' => $limit,
            'planned_total' => $plannedTotal,
            'spent_total' => $spentTotal,
            'categories' => $categories,
            'alerts' => $alerts,
            'expenses' => $budget->expenses,
        ];
    }

    private function emergencyBuffer(int|float $subtotal): int
    {
        return (int) round($subtotal * self::EMERGENCY_SHARE);
    }

    /**
     * Largest subtotal whose total with the emergency buffer still fits the limit.
     */
    private function subtotalCapacity(float $limit): int
    {
        $capacity = (int) floor($limit / (1 + self::EMERGENCY_SHARE)) + 2;

        while ($capacity >= 0 && $capacity + $this->emergencyBuffer($capacity) > $limit) {
            $capacity--;
        }

        return $capacity;
    }

    /**
     * @param  array<string, array<string, int>>  $tierCosts
     * @param  list<string>  $categories
     * @param  list<string>  $allowedTiers
     * @return list<list<array{tier: string, cost: int, value: int}>>
     */
    private function swapOptions(array $tierCosts, array $categories, array $allowedTiers, string $comfort): array
    {
        $options = [];

        foreach ($categories as $category) {
            $costs = array_map(fn (string $tier): int => $tierCosts[$category][$tier], $allowedTiers);
            $weight = self::CATEGORY_WEIGHT[$category];

            if (count(array_unique($costs)) === 1) {
                $options[] = [[
                    'tier' => $comfort,
                    'cost' => $tierCosts[$category][$comfort],
                    'value' => $weight * self::TIER_VALUE[$comfort],
                ]];

                continue;
            }

            $options[] = array_map(fn (string $tier): array => [
                'tier' => $tier,
                'cost' => $tierCosts[$category][$tier],
                'value' => $weight * self::TIER_VALUE[$tier],
            ], $allowedTiers);
        }

        return $options;
    }

    /**
     * @param  list<list<array{tier: string, cost: int, value: int}>>  $options
     */
    private function sumOfExpensiveOptions(array $options): int
    {
        return array_sum(array_map(
            fn (array $categoryOptions): int => max(array_column($categoryOptions, 'cost')),
            $options,
        ));
    }

    /**
     * @param  list<array{tier: string, cost: int, value: int}>  $categoryOptions
     */
    private function cheapestOptionIndex(array $categoryOptions): int
    {
        $costs = array_column($categoryOptions, 'cost');

        return (int) array_search(min($costs), $costs, true);
    }

    /**
     * Multiple-choice knapsack by dynamic programming over whole-dollar costs.
     * Returns the chosen option index per category, or null if nothing fits.
     *
     * @param  list<list<array{tier: string, cost: int, value: int}>>  $options
     * @return list<int>|null
     */
    private function solveKnapsack(array $options, int $capacity): ?array
    {
        $best = array_fill(0, $capacity + 1, 0);
        $picks = [];

        foreach ($options as $categoryIndex => $categoryOptions) {
            $next = array_fill(0, $capacity + 1, -1);
            $picks[$categoryIndex] = array_fill(0, $capacity + 1, -1);

            for ($budget = 0; $budget <= $capacity; $budget++) {
                foreach ($categoryOptions as $optionIndex => $option) {
                    if ($option['cost'] > $budget || $best[$budget - $option['cost']] < 0) {
                        continue;
                    }

                    $value = $best[$budget - $option['cost']] + $option['value'];
                    if ($value > $next[$budget]) {
                        $next[$budget] = $value;
                        $picks[$categoryIndex][$budget] = $optionIndex;
                    }
                }
            }

            $best = $next;
        }

        if ($best[$capacity] < 0) {
            return null;
        }

        $chosen = [];
        $remaining = $capacity;

        for ($categoryIndex = count($options) - 1; $categoryIndex >= 0; $categoryIndex--) {
            $optionIndex = $picks[$categoryIndex][$remaining];
            $chosen[$categoryIndex] = $optionIndex;
            $remaining -= $options[$categoryIndex][$optionIndex]['cost'];
        }

        ksort($chosen);

        return array_values($chosen);
    }

    private function money(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }
}
