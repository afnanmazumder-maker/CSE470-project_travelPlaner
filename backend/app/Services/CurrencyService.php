<?php

namespace App\Services;

use App\Exceptions\CurrencyUnavailableException;
use App\Models\ExchangeRate;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * R8: converts amounts with Frankfurter daily reference rates.
 *
 * Rates are cached in exchange_rates (all quoted against EUR) and refreshed
 * by the scheduled rates:refresh command. Each date is fetched from
 * Frankfurter once; if it is unreachable the newest stored rate is used and
 * the expense records that it used a fallback rate.
 */
class CurrencyService
{
    public const BASE = 'EUR';

    public function supported(): array
    {
        return config('currency.currencies');
    }

    /**
     * @return array{rate: float, rate_date: Carbon, source: string}
     */
    public function rate(string $from, string $to, ?CarbonInterface $date = null): array
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        $date = Carbon::parse($date ?? Carbon::today())->startOfDay();

        if ($from === $to) {
            return ['rate' => 1.0, 'rate_date' => $date, 'source' => 'identity'];
        }

        $fromRate = $this->eurRate($from, $date);
        $toRate = $this->eurRate($to, $date);

        // EUR->to divided by EUR->from gives from->to.
        return [
            'rate' => round($toRate->rate / $fromRate->rate, 8),
            'rate_date' => $fromRate->rate_date->max($toRate->rate_date),
            'source' => in_array('fallback', [$fromRate->source, $toRate->source], true) ? 'fallback' : 'frankfurter',
        ];
    }

    /**
     * @return array{rate: float, rate_date: Carbon, source: string, converted: float}
     */
    public function convert(float $amount, string $from, string $to, ?CarbonInterface $date = null): array
    {
        $rate = $this->rate($from, $to, $date);

        return $rate + ['converted' => round($amount * $rate['rate'], 2)];
    }

    /** Pulls today's rates from Frankfurter; returns how many were stored. */
    public function refreshLatest(): int
    {
        return $this->fetch(Carbon::today(), force: true);
    }

    protected function eurRate(string $currency, Carbon $date): ExchangeRate
    {
        if ($currency === self::BASE) {
            return new ExchangeRate(['base' => self::BASE, 'quote' => self::BASE, 'rate' => 1, 'rate_date' => $date, 'source' => 'identity']);
        }

        $cached = $this->storedOnOrBefore($currency, $date);
        // Frankfurter answers a weekend or holiday with the previous business day, so a
        // date counts as covered once it has been fetched, even if no row carries that date.
        $fetchedKey = 'frankfurter:fetched:'.$date->toDateString();

        if ($cached && $cached->source === 'frankfurter' && ($cached->rate_date->isSameDay($date) || Cache::has($fetchedKey))) {
            return $cached;
        }

        if ($this->fetch($date) > 0) {
            Cache::put($fetchedKey, true, $date->isToday() ? now()->endOfDay() : now()->addDays(30));

            if ($fresh = $this->storedOnOrBefore($currency, $date)) {
                return $fresh;
            }
        }

        $fallback = $cached ?? ExchangeRate::where('base', self::BASE)->where('quote', $currency)->orderByDesc('rate_date')->first();

        if (! $fallback) {
            throw new CurrencyUnavailableException("No exchange rate is available for {$currency}.");
        }

        return $fallback;
    }

    protected function storedOnOrBefore(string $currency, Carbon $date): ?ExchangeRate
    {
        return ExchangeRate::where('base', self::BASE)
            ->where('quote', $currency)
            ->whereDate('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->first();
    }

    /** Fetches one day of rates (or the latest) and caches them. */
    protected function fetch(Carbon $date, bool $force = false): int
    {
        $path = $date->gte(Carbon::today()) ? 'latest' : $date->toDateString();
        $failKey = "frankfurter:failed:{$path}";

        if (! $force && Cache::has($failKey)) {
            return 0;
        }

        try {
            $response = Http::timeout(config('currency.frankfurter.timeout'))
                ->acceptJson()
                ->get(rtrim(config('currency.frankfurter.url'), '/')."/{$path}", ['base' => self::BASE]);
        } catch (ConnectionException $e) {
            Log::warning('Frankfurter unreachable', ['path' => $path, 'error' => $e->getMessage()]);
            Cache::put($failKey, true, now()->addMinutes(10));

            return 0;
        }

        if (! $response->successful() || ! is_array($response->json('rates'))) {
            Cache::put($failKey, true, now()->addMinutes(10));

            return 0;
        }

        return $this->store($response->json('date'), $response->json('rates'), 'frankfurter');
    }

    /** @param array<string, float> $rates */
    public function store(string $rateDate, array $rates, string $source): int
    {
        $now = now();
        $rows = collect($rates)->map(fn ($rate, $quote) => [
            'base' => self::BASE,
            'quote' => $quote,
            'rate' => $rate,
            'rate_date' => $rateDate,
            'source' => $source,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();

        ExchangeRate::upsert($rows, ['base', 'quote', 'rate_date'], ['rate', 'source', 'updated_at']);

        return count($rows);
    }
}
