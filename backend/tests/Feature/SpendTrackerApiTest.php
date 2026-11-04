<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Destination;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\DestinationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpendTrackerApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Budget $budget;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-12-04 12:00');
        $this->seed(DestinationSeeder::class);
        $this->fakeRates();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
        $this->budget = $this->user->budgets()->create([
            'title' => 'Tokyo trip', 'destination_id' => Destination::where('name', 'Tokyo')->value('id'),
            'days' => 5, 'travelers' => 1, 'comfort' => 'moderate',
            'planned' => ['flights' => 500, 'food' => 100, 'shopping' => 100, 'stay' => 0],
        ]);
    }

    /** Frankfurter answers every date with the same EUR-based rates; weekends answer with Friday. */
    private function fakeRates(array $rates = ['USD' => 1.12, 'JPY' => 175.0]): void
    {
        Http::fake(['api.frankfurter.dev/*' => function (Request $request) use ($rates) {
            $path = basename(parse_url($request->url(), PHP_URL_PATH));
            $date = $path === 'latest' ? Carbon::today() : Carbon::parse($path);
            while ($date->isWeekend()) {
                $date->subDay();
            }

            return Http::response(['amount' => 1.0, 'base' => 'EUR', 'date' => $date->toDateString(), 'rates' => $rates]);
        }]);
    }

    private function addExpense(array $overrides = []): TestResponse
    {
        return $this->postJson("/api/budgets/{$this->budget->id}/expenses", array_merge([
            'category' => 'food', 'description' => 'Sushi lunch', 'amount' => 3500, 'currency' => 'JPY', 'spent_on' => '2026-12-03',
        ], $overrides));
    }

    public function test_a_foreign_expense_is_converted_with_that_days_rate(): void
    {
        $this->addExpense()
            ->assertCreated()
            ->assertJsonPath('expense.amount', 22.4)
            ->assertJsonPath('expense.original_amount', 3500)
            ->assertJsonPath('expense.currency', 'JPY')
            ->assertJsonPath('expense.rate_used', round(1.12 / 175, 8))
            ->assertJsonPath('expense.rate_source', 'frankfurter')
            ->assertJsonPath('expense.spent_on', '2026-12-03')
            ->assertJsonPath('budget.spent_total', 22.4);
    }

    public function test_usd_expenses_need_no_rate_lookup(): void
    {
        Http::fake();

        $this->addExpense(['currency' => null, 'amount' => 12])->assertCreated()->assertJsonPath('expense.amount', 12);

        Http::assertNothingSent();
    }

    public function test_an_empty_date_means_today_instead_of_a_server_error(): void
    {
        // Regression: the R7 page sends spent_on: null when the date box is empty.
        $this->addExpense(['spent_on' => null, 'currency' => 'USD', 'amount' => 5])
            ->assertCreated()
            ->assertJsonPath('expense.spent_on', '2026-12-04');
    }

    public function test_unsupported_currency_and_missing_rates_are_rejected(): void
    {
        $this->addExpense(['currency' => 'BDT'])->assertUnprocessable()->assertJsonValidationErrors('currency');

        Http::fake(fn () => throw new ConnectionException('down'));
        $this->addExpense(['spent_on' => '2026-11-20'])->assertUnprocessable()->assertJsonValidationErrors('currency');
    }

    public function test_spending_outside_the_plan_is_unplanned(): void
    {
        $this->addExpense(['category' => 'other', 'description' => 'SIM card', 'currency' => 'USD', 'amount' => 15])->assertCreated();

        $this->getJson("/api/budgets/{$this->budget->id}/deviation")
            ->assertOk()
            ->assertJsonPath('unplanned.count', 1)
            ->assertJsonPath('unplanned.status', 'unplanned')
            ->assertJsonPath('totals.actual', 15);

        // R7's own summary still works and counts it in the total.
        $this->getJson("/api/budgets/{$this->budget->id}")->assertOk()->assertJsonPath('spent_total', 15);
    }

    public function test_deviation_per_category_and_day_once_the_start_date_is_set(): void
    {
        $this->addExpense(['currency' => 'USD', 'amount' => 40, 'spent_on' => '2026-12-02']);
        $this->addExpense(['category' => 'flights', 'currency' => 'USD', 'amount' => 480, 'spent_on' => '2026-11-20']);

        $this->getJson("/api/budgets/{$this->budget->id}/deviation")->assertOk()->assertJsonPath('days', []);

        $summary = $this->putJson("/api/budgets/{$this->budget->id}/tracking", ['start_date' => '2026-12-01', 'alert_threshold_pct' => 10])
            ->assertOk()
            ->json('summary');

        $food = collect($summary['categories'])->firstWhere('category', 'food');
        $this->assertSame(['planned' => 100, 'actual' => 40, 'deviation' => -60, 'deviation_pct' => -60, 'status' => 'within'], array_intersect_key($food, array_flip(['planned', 'actual', 'deviation', 'deviation_pct', 'status'])));

        $days = collect($summary['days'])->keyBy('day');
        $this->assertSame(500, (int) $days[0]['planned']);
        $this->assertSame(480, (int) $days[0]['actual']);
        $this->assertSame('2026-12-02', $days[2]['date']);
        $this->assertSame(40, (int) $days[2]['actual']);
        $this->assertSame(4, $summary['tracking']['today_day_number']);
        $this->assertEqualsWithDelta($summary['totals']['actual'], collect($summary['days'])->sum('actual'), 0.01);
    }

    public function test_threshold_alert_fires_once_per_crossing_and_re_arms(): void
    {
        $big = $this->addExpense(['currency' => 'USD', 'amount' => 120])
            ->assertJsonPath('deviation_alerts.0', 'Food is 20% over plan')
            ->json('expense.id');

        $this->addExpense(['currency' => 'USD', 'amount' => 5])->assertJsonPath('deviation_alerts', []);
        $this->assertSame(1, Notification::where('type', 'budget_alert')->count());

        $this->deleteJson("/api/budgets/{$this->budget->id}/expenses/{$big}")->assertOk();
        $this->addExpense(['currency' => 'USD', 'amount' => 120])->assertJsonCount(1, 'deviation_alerts');

        $this->assertSame(2, Notification::where('type', 'budget_alert')->where('user_id', $this->user->id)->count());
    }

    public function test_raising_the_threshold_clears_the_alert_state(): void
    {
        $this->addExpense(['currency' => 'USD', 'amount' => 120]);

        $summary = $this->putJson("/api/budgets/{$this->budget->id}/tracking", ['alert_threshold_pct' => 50])->assertOk()->json('summary');

        $this->assertSame('over', collect($summary['categories'])->firstWhere('category', 'food')['status']);
        $this->addExpense(['currency' => 'USD', 'amount' => 1])->assertJsonPath('deviation_alerts', []);
    }

    public function test_variance_report_and_csv_export(): void
    {
        $this->addExpense();
        $this->addExpense(['category' => 'other', 'description' => 'Umbrella', 'currency' => 'USD', 'amount' => 8]);

        $this->getJson("/api/budgets/{$this->budget->id}/variance-report")
            ->assertOk()
            ->assertJsonStructure(['totals', 'categories', 'unplanned', 'days', 'cumulative', 'by_currency', 'top_expenses', 'expenses', 'insights'])
            ->assertJsonPath('by_currency.0.currency', 'JPY')
            ->assertJsonPath('by_currency.0.original_total', 3500);

        $csv = $this->get("/api/budgets/{$this->budget->id}/variance-report.csv")->assertOk();
        $this->assertStringStartsWith('text/csv', $csv->headers->get('Content-Type'));
        $this->assertStringContainsString('"Voyenta variance report","Tokyo trip",Tokyo', $csv->getContent());
        $this->assertStringContainsString('Sushi lunch', $csv->getContent());
        $this->assertStringContainsString('Miscellaneous / Unplanned', $csv->getContent());
    }

    public function test_other_travelers_cannot_see_or_change_the_tracking(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/budgets/{$this->budget->id}/deviation")->assertForbidden();
        $this->putJson("/api/budgets/{$this->budget->id}/tracking", ['alert_threshold_pct' => 30])->assertForbidden();
        $this->get("/api/budgets/{$this->budget->id}/variance-report.csv")->assertForbidden();
        $this->addExpense()->assertForbidden();
    }
}
