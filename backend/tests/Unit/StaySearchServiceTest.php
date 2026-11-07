<?php

namespace Tests\Unit;

use App\Services\StaySearchService;
use PHPUnit\Framework\TestCase;

class StaySearchServiceTest extends TestCase
{
    public function test_paris_to_rome_is_about_1105_km(): void
    {
        $this->assertEqualsWithDelta(1105, StaySearchService::distanceKm(48.8566, 2.3522, 41.9028, 12.4964), 5);
    }

    public function test_the_same_point_is_zero_km_apart(): void
    {
        $this->assertSame(0.0, StaySearchService::distanceKm(-8.4095, 115.1889, -8.4095, 115.1889));
    }
}
