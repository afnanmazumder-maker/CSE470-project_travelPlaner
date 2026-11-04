<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetTracking;
use App\Models\Expense;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * R8: compares actual spending with the R7 plan per category, per trip day
 * and for the whole trip, flags unplanned spending (category "other") and
 * raises an alert once each time a category passes the threshold.
 */
class DeviationService
{
    /** Categories paid before the trip, planned on day 0 instead of spread over the days. */
    public const PRE_TRIP_CATEGORIES = ['flights', 'visa', 'insurance'];

    public const UNPLANNED = 'other';

    public const UNPLANNED_LABEL = 'Miscellaneous / Unplanned';

    public function __construct(private NotificationService $notifications) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(Budget $budget): array
    {
        $budget->load(['destination:id,name,country', 'expenses' => fn ($query) => $query->orderBy('spent_on')->orderBy('id')]);
        $tracking = BudgetTracking::forBudget($budget);
        $threshold = $tracking->alert_threshold_pct;
        $expenses = $budget->expenses;

        $categories = collect(BudgetService::CATEGORIES)->map(function (string $category) use ($budget, $expenses, $threshold): array {
            $own = $expenses->where('category', $category);

            return ['category' => $category, 'name' => $this->label($category), 'count' => $own->count()]
                + $this->deviation((float) ($budget->planned[$category] ?? 0), (float) $own->sum('amount'), $threshold);
        });

        $unplanned = $expenses->where('category', self::UNPLANNED);
        $plannedTotal = (float) array_sum($budget->planned);
        $todayNumber = $tracking->dayNumberFor(Carbon::today(), $budget->days);
        $days = $tracking->start_date ? $this->dayRows($budget, $tracking, $expenses, $threshold) : collect();

        return [
            'budget' => [
                'id' => $budget->id,
                'title' => $budget->title,
                'destination' => $budget->destination,
                'days' => $budget->days,
                'travelers' => $budget->travelers,
            ],
            'tracking' => [
                'start_date' => $tracking->start_date?->toDateString(),
                'end_date' => $tracking->start_date?->copy()->addDays($budget->days - 1)->toDateString(),
                'alert_threshold_pct' => $threshold,
                'today_day_number' => $todayNumber,
            ],
            'home_currency' => config('currency.home'),
            'totals' => $this->deviation($plannedTotal, (float) $expenses->sum('amount'), $threshold) + [
                'planned_to_date' => $todayNumber === null ? null : round((float) $days->where('day', '<=', $todayNumber)->sum('planned'), 2),
                'expense_count' => $expenses->count(),
            ],
            'categories' => $categories->values(),
            'unplanned' => ['category' => self::UNPLANNED, 'name' => self::UNPLANNED_LABEL, 'count' => $unplanned->count()]
                + $this->deviation(0, (float) $unplanned->sum('amount'), $threshold),
            'days' => $days->values(),
        ];
    }

    /**
     * End-of-trip variance report: the summary plus the breakdowns the charts and exports use.
     *
     * @return array<string, mixed>
     */
    public function report(Budget $budget): array
    {
        $summary = $this->summary($budget);
        $expenses = $budget->expenses;
        $tracking = BudgetTracking::forBudget($budget);

        $plannedSoFar = 0.0;
        $actualSoFar = 0.0;
        $cumulative = collect($summary['days'])->map(function (array $day) use (&$plannedSoFar, &$actualSoFar): array {
            $plannedSoFar += $day['planned'];
            $actualSoFar += $day['actual'];

            return ['label' => $day['label'], 'planned' => round($plannedSoFar, 2), 'actual' => round($actualSoFar, 2)];
        });

        $byCurrency = $expenses->groupBy('currency')->map(fn (Collection $rows, string $currency): array => [
            'currency' => $currency,
            'count' => $rows->count(),
            'original_total' => round((float) $rows->sum(fn (Expense $e) => $e->original_amount ?? $e->amount), 2),
            'converted_total' => round((float) $rows->sum('amount'), 2),
        ])->values();

        $rows = $expenses->map(fn (Expense $e): array => $this->expenseRow($e, $tracking, $budget->days));

        return $summary + [
            'generated_at' => now()->toIso8601String(),
            'cumulative' => $cumulative->values(),
            'by_currency' => $byCurrency,
            'top_expenses' => $rows->sortByDesc('amount')->take(5)->values(),
            'expenses' => $rows->values(),
            'insights' => $this->insights($summary),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function toCsv(array $report): string
    {
        $out = fopen('php://temp', 'r+');

        fputcsv($out, ['Voyenta variance report', $report['budget']['title'], $report['budget']['destination']['name'] ?? '']);
        fputcsv($out, []);
        fputcsv($out, ['Category', 'Planned (USD)', 'Actual (USD)', 'Deviation', 'Deviation %', 'Status']);
        foreach ([...$report['categories'], $report['unplanned']] as $row) {
            fputcsv($out, [$row['name'], $row['planned'], $row['actual'], $row['deviation'], $row['deviation_pct'], $row['status']]);
        }
        $totals = $report['totals'];
        fputcsv($out, ['Whole trip', $totals['planned'], $totals['actual'], $totals['deviation'], $totals['deviation_pct'], $totals['status']]);

        if ($report['days']->isNotEmpty()) {
            fputcsv($out, []);
            fputcsv($out, ['Day', 'Date', 'Planned', 'Actual', 'Deviation', 'Deviation %']);
            foreach ($report['days'] as $day) {
                fputcsv($out, [$day['label'], $day['date'], $day['planned'], $day['actual'], $day['deviation'], $day['deviation_pct']]);
            }
        }

        fputcsv($out, []);
        fputcsv($out, ['Date', 'Day', 'Description', 'Category', 'Paid', 'Currency', 'Rate used', 'Rate date', 'USD']);
        foreach ($report['expenses'] as $e) {
            fputcsv($out, [$e['spent_on'], $e['day_label'], $e['description'], $e['category_name'], $e['original_amount'], $e['currency'], $e['rate_used'], $e['rate_date'], $e['amount']]);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * Raises a budget_alert (R16) each time a category or the whole trip crosses
     * the threshold, and re-arms it when spending falls back under.
     *
     * @return list<string> titles of the alerts raised
     */
    public function checkThresholds(Budget $budget): array
    {
        $summary = $this->summary($budget);
        $tracking = BudgetTracking::forBudget($budget);
        $alerted = $tracking->alerted_categories ?? [];
        $raised = [];

        $rows = $summary['categories']->keyBy('category')->put('total', ['name' => 'Whole trip'] + $summary['totals']);

        foreach ($rows as $key => $row) {
            $isAlert = $row['status'] === 'alert';
            $wasAlert = in_array($key, $alerted, true);

            if ($isAlert && ! $wasAlert) {
                $alerted[] = $key;
                $raised[] = $this->alert($budget, $row);
            } elseif (! $isAlert && $wasAlert) {
                $alerted = array_values(array_diff($alerted, [$key]));
            }
        }

        $tracking->update(['alerted_categories' => $alerted]);

        return $raised;
    }

    /**
     * @return array{planned: float, actual: float, deviation: float, deviation_pct: float|null, status: string}
     */
    public function deviation(float $planned, float $actual, int $threshold): array
    {
        $amount = round($actual - $planned, 2);

        $status = match (true) {
            $planned <= 0 && $actual > 0 => 'unplanned',
            $planned <= 0 => 'within',
            $actual > $planned * (1 + $threshold / 100) => 'alert',
            $actual > $planned => 'over',
            default => 'within',
        };

        return [
            'planned' => round($planned, 2),
            'actual' => round($actual, 2),
            'deviation' => $amount,
            'deviation_pct' => $planned > 0 ? round($amount / $planned * 100, 1) : null,
            'status' => $status,
        ];
    }

    /**
     * Planned spend per trip day: pre-trip categories on day 0, the rest spread evenly.
     *
     * @param  array<string, int|float>  $planned
     * @return array<int, float>
     */
    public function plannedByDay(array $planned, int $days): array
    {
        $plan = [0 => 0.0] + array_fill(1, $days, 0.0);

        foreach ($planned as $category => $amount) {
            if (in_array($category, self::PRE_TRIP_CATEGORIES, true)) {
                $plan[0] += $amount;
            } else {
                for ($day = 1; $day <= $days; $day++) {
                    $plan[$day] += $amount / $days;
                }
            }
        }

        return array_map(fn (float $value): float => round($value, 2), $plan);
    }

    /**
     * @param  Collection<int, Expense>  $expenses
     * @return Collection<int, array<string, mixed>>
     */
    private function dayRows(Budget $budget, BudgetTracking $tracking, Collection $expenses, int $threshold): Collection
    {
        $planned = $this->plannedByDay($budget->planned, $budget->days);
        $actual = $expenses->groupBy(fn (Expense $e): int => $tracking->dayNumberFor($e->spent_on, $budget->days))
            ->map(fn (Collection $rows): float => (float) $rows->sum('amount'));

        return collect(array_keys($planned))
            ->merge($actual->keys())
            ->unique()
            ->sort()
            ->values()
            ->map(fn (int $day): array => [
                'day' => $day,
                'label' => $this->dayLabel($day, $budget->days),
                'date' => $day >= 1 && $day <= $budget->days ? $tracking->start_date->copy()->addDays($day - 1)->toDateString() : null,
            ] + $this->deviation($planned[$day] ?? 0, $actual[$day] ?? 0, $threshold));
    }

    private function dayLabel(int $day, int $days): string
    {
        return match (true) {
            $day === 0 => 'Pre-trip',
            $day > $days => 'After trip',
            default => "Day {$day}",
        };
    }

    private function label(string $category): string
    {
        return $category === 'emergency' ? 'Emergency buffer' : ucfirst($category);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function alert(Budget $budget, array $row): string
    {
        $title = "{$row['name']} is {$row['deviation_pct']}% over plan";

        $this->notifications->notify(
            $budget->user,
            'budget_alert',
            $title,
            sprintf(
                'You have spent $%s against a plan of $%s on "%s" ($%s over).',
                number_format($row['actual'], 2), number_format($row['planned'], 2), $budget->title, number_format($row['deviation'], 2),
            ),
            ['row' => $row],
            $budget,
        );

        return $title;
    }

    /**
     * @return array<string, mixed>
     */
    private function expenseRow(Expense $expense, BudgetTracking $tracking, int $days): array
    {
        $day = $tracking->dayNumberFor($expense->spent_on, $days);

        return [
            'id' => $expense->id,
            'spent_on' => $expense->spent_on->toDateString(),
            'day_label' => $day === null ? '' : $this->dayLabel($day, $days),
            'description' => $expense->description,
            'category' => $expense->category,
            'category_name' => $expense->category === self::UNPLANNED ? self::UNPLANNED_LABEL : $this->label($expense->category),
            'amount' => $expense->amount,
            'currency' => $expense->currency,
            'original_amount' => $expense->original_amount ?? $expense->amount,
            'rate_used' => $expense->rate_used,
            'rate_date' => $expense->rate_date?->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<string>
     */
    private function insights(array $summary): array
    {
        $insights = [];
        $totals = $summary['totals'];

        if ($totals['planned'] > 0) {
            $insights[] = $totals['deviation'] > 0
                ? sprintf('The trip is $%s (%s%%) over the planned budget.', number_format($totals['deviation'], 2), $totals['deviation_pct'])
                : sprintf('The trip is $%s (%s%%) under the planned budget.', number_format(abs($totals['deviation']), 2), abs($totals['deviation_pct']));
        }

        $worst = $summary['categories']->where('deviation', '>', 0)->sortByDesc('deviation_pct')->first();
        if ($worst) {
            $insights[] = sprintf('Biggest overspend: %s at %s%% over plan.', $worst['name'], $worst['deviation_pct']);
        }

        $best = $summary['categories']->where('actual', '>', 0)->where('deviation', '<', 0)->sortBy('deviation_pct')->first();
        if ($best) {
            $insights[] = sprintf('Most savings: %s at %s%% under plan.', $best['name'], abs($best['deviation_pct']));
        }

        if ($summary['unplanned']['count'] > 0) {
            $insights[] = sprintf('%d unplanned expenses added $%s outside the planned categories.', $summary['unplanned']['count'], number_format($summary['unplanned']['actual'], 2));
        }

        if ($summary['days']->isEmpty()) {
            $insights[] = 'Set the trip start date to see spending per day.';
        }

        return $insights;
    }
}
