<?php

namespace Tests\Unit;

use App\Exceptions\CurrencyUnavailableException;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurrencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakeFrankfurter(string $date, array $rates = ['USD' => 1.12, 'JPY' => 175.0, 'THB' => 37.5]): void
    {
        Http::fake(['api.frankfurter.dev/*' => Http::response(['amount' => 1.0, 'base' => 'EUR', 'date' => $date, 'rates' => $rates])]);
    }

    public function test_cross_rate_is_derived_from_eur_rates(): void
    {
        $this->fakeFrankfurter('2026-12-03');

        $result = app(CurrencyService::class)->convert(10000, 'JPY', 'USD', Carbon::parse('2026-12-03'));

        $this->assertSame(round(1.12 / 175, 8), $result['rate']);
        $this->assertSame(64.0, $result['converted']);
        $this->assertSame('frankfurter', $result['source']);
    }

    public function test_each_date_is_fetched_once_and_a_weekend_uses_friday(): void
    {
        $this->fakeFrankfurter('2026-12-04');
        $currency = app(CurrencyService::class);
        $saturday = Carbon::parse('2026-12-05');

        $first = $currency->rate('THB', 'USD', $saturday);
        $currency->rate('THB', 'USD', $saturday);
        $currency->rate('JPY', 'USD', $saturday);

        Http::assertSentCount(1);
        $this->assertSame('2026-12-04', $first['rate_date']->toDateString());
    }

    public function test_frankfurter_down_falls_back_to_the_newest_stored_rate(): void
    {
        Http::fake(fn () => throw new ConnectionException('Frankfurter is down'));
        $currency = app(CurrencyService::class);
        $currency->store('2026-01-02', ['USD' => 1.10, 'JPY' => 170.0], 'fallback');

        $result = $currency->convert(1700, 'JPY', 'USD', Carbon::parse('2026-12-03'));

        $this->assertSame('fallback', $result['source']);
        $this->assertSame(11.0, $result['converted']);
    }

    public function test_no_rate_anywhere_throws(): void
    {
        Http::fake(fn () => throw new ConnectionException('Frankfurter is down'));

        $this->expectException(CurrencyUnavailableException::class);

        app(CurrencyService::class)->rate('JPY', 'USD', Carbon::parse('2026-12-03'));
    }
}
