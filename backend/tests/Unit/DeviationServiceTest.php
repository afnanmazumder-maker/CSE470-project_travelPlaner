<?php

namespace Tests\Unit;

use App\Services\DeviationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeviationServiceTest extends TestCase
{
    private DeviationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DeviationService;
    }

    /** @return array<string, array{float, float, string, float|null}> */
    public static function boundaries(): array
    {
        return [
            'under plan' => [100, 80, 'within', -20.0],
            'exactly on plan' => [100, 100, 'within', 0.0],
            'over but under the threshold' => [100, 105, 'over', 5.0],
            'exactly at the threshold' => [100, 110, 'over', 10.0],
            'just past the threshold' => [100, 110.01, 'alert', 10.0],
            'unplanned spending' => [0, 25, 'unplanned', null],
            'nothing planned or spent' => [0, 0, 'within', null],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_status_and_percentage_at_each_boundary(float $planned, float $actual, string $status, ?float $percent): void
    {
        $row = $this->service->deviation($planned, $actual, 10);

        $this->assertSame($status, $row['status']);
        $this->assertSame($percent, $row['deviation_pct']);
    }

    public function test_pre_trip_categories_land_on_day_zero_and_the_rest_is_spread(): void
    {
        $plan = $this->service->plannedByDay(['flights' => 600, 'visa' => 50, 'food' => 300, 'activities' => 90], 3);

        $this->assertSame([0 => 650.0, 1 => 130.0, 2 => 130.0, 3 => 130.0], $plan);
    }
}
