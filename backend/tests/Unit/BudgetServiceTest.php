<?php

namespace Tests\Unit;

use App\Services\BudgetService;
use PHPUnit\Framework\TestCase;

class BudgetServiceTest extends TestCase
{
    private BudgetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BudgetService;
    }

    public function test_estimate_covers_every_category(): void
    {
        $planned = $this->service->estimate(80, 5, 2, 'moderate');

        $this->assertSame(BudgetService::CATEGORIES, array_keys($planned));
    }

    public function test_emergency_buffer_is_ten_percent_of_the_rest(): void
    {
        $planned = $this->service->estimate(80, 5, 2, 'moderate');
        $emergency = $planned['emergency'];
        unset($planned['emergency']);

        $this->assertSame((int) round(array_sum($planned) * 0.10), $emergency);
    }

    public function test_cost_grows_with_cost_index_travelers_and_comfort(): void
    {
        $base = array_sum($this->service->estimate(50, 5, 2, 'moderate'));

        $this->assertGreaterThan($base, array_sum($this->service->estimate(90, 5, 2, 'moderate')));
        $this->assertGreaterThan($base, array_sum($this->service->estimate(50, 5, 4, 'moderate')));
        $this->assertGreaterThan($base, array_sum($this->service->estimate(50, 5, 2, 'luxury')));
        $this->assertLessThan($base, array_sum($this->service->estimate(50, 5, 2, 'budget')));
    }

    public function test_no_swaps_when_the_plan_already_fits(): void
    {
        $tierCosts = $this->service->tierCosts(70, 5, 2);
        $total = array_sum($this->service->estimate(70, 5, 2, 'luxury'));

        $result = $this->service->suggestSwaps($tierCosts, 'luxury', $total + 500);

        $this->assertTrue($result['achievable']);
        $this->assertSame([], $result['swaps']);
        $this->assertSame(0, $result['savings']);
    }

    public function test_swaps_bring_the_total_under_the_limit(): void
    {
        $tierCosts = $this->service->tierCosts(70, 5, 2);
        $total = array_sum($this->service->estimate(70, 5, 2, 'luxury'));
        $limit = $total * 0.8;

        $result = $this->service->suggestSwaps($tierCosts, 'luxury', $limit);

        $this->assertTrue($result['achievable']);
        $this->assertLessThanOrEqual($limit, $result['new_total']);
        $this->assertNotEmpty($result['swaps']);
        $this->assertSame($result['current_total'] - $result['new_total'], $result['savings']);
    }

    public function test_swaps_only_move_to_cheaper_tiers(): void
    {
        $tierCosts = $this->service->tierCosts(70, 5, 2);
        $total = array_sum($this->service->estimate(70, 5, 2, 'moderate'));

        $result = $this->service->suggestSwaps($tierCosts, 'moderate', $total * 0.8);

        foreach ($result['swaps'] as $swap) {
            $this->assertSame('moderate', $swap['from']);
            $this->assertSame('budget', $swap['to']);
            $this->assertGreaterThan(0, $swap['saves']);
        }
    }

    public function test_limit_below_the_cheapest_plan_is_not_achievable(): void
    {
        $tierCosts = $this->service->tierCosts(70, 5, 2);

        $result = $this->service->suggestSwaps($tierCosts, 'luxury', 100);

        $this->assertFalse($result['achievable']);
        $this->assertGreaterThan(100, $result['new_total']);
    }

    public function test_optimizer_matches_brute_force_comfort_value(): void
    {
        $tierCosts = $this->service->tierCosts(60, 4, 3);
        $tiers = ['budget', 'moderate', 'luxury'];
        $weights = [
            'flights' => 3, 'stay' => 3, 'food' => 2, 'activities' => 2,
            'transport' => 1, 'shopping' => 1, 'visa' => 1, 'insurance' => 1,
        ];
        $total = array_sum($this->service->estimate(60, 4, 3, 'luxury'));

        foreach ([0.55, 0.7, 0.85] as $share) {
            $limit = $total * $share;
            $result = $this->service->suggestSwaps($tierCosts, 'luxury', $limit);

            if (! $result['achievable']) {
                continue;
            }

            $swapped = array_column($result['swaps'], 'to', 'category');
            $optimizerValue = 0;
            foreach ($weights as $category => $weight) {
                $optimizerValue += $weight * (array_search($swapped[$category] ?? 'luxury', $tiers, true) + 1);
            }

            $this->assertSame($this->bruteForceBestValue($tierCosts, $weights, $tiers, $limit), $optimizerValue);
        }
    }

    /**
     * @param  array<string, array<string, int>>  $tierCosts
     * @param  array<string, int>  $weights
     * @param  list<string>  $tiers
     */
    private function bruteForceBestValue(array $tierCosts, array $weights, array $tiers, float $limit): int
    {
        $categories = array_keys($weights);
        $best = -1;

        $search = function (int $index, int $subtotal, int $value) use (&$search, &$best, $categories, $weights, $tiers, $tierCosts, $limit): void {
            if ($index === count($categories)) {
                if ($subtotal + (int) round($subtotal * 0.10) <= $limit) {
                    $best = max($best, $value);
                }

                return;
            }

            $category = $categories[$index];
            $seenCosts = [];

            foreach ($tiers as $tierIndex => $tier) {
                $cost = $tierCosts[$category][$tier];
                $isFixedCostCategory = count(array_unique($tierCosts[$category])) === 1;

                if ($isFixedCostCategory && $tier !== 'luxury') {
                    continue;
                }

                $search($index + 1, $subtotal + $cost, $value + $weights[$category] * ($tierIndex + 1));
            }
        };

        $search(0, 0, 0);

        return $best;
    }
}
