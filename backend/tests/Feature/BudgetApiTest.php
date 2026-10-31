<?php

namespace Tests\Feature;

use App\Models\Destination;
use App\Models\User;
use Database\Seeders\DestinationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BudgetApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Destination $paris;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DestinationSeeder::class);
        $this->user = User::factory()->create();
        $this->paris = Destination::where('name', 'Paris')->firstOrFail();
        Sanctum::actingAs($this->user);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Paris trip',
            'destination_id' => $this->paris->id,
            'days' => 5,
            'travelers' => 2,
            'comfort' => 'moderate',
            'limit_amount' => 3000,
        ], $overrides);
    }

    private function createBudget(array $overrides = []): int
    {
        return $this->postJson('/api/budgets', $this->payload($overrides))->json('id');
    }

    public function test_budget_routes_require_login(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/budgets')->assertUnauthorized();
    }

    public function test_estimate_returns_planned_cost_per_category(): void
    {
        $this->postJson('/api/budgets/estimate', [
            'destination_id' => $this->paris->id,
            'days' => 5,
            'travelers' => 2,
            'comfort' => 'moderate',
        ])
            ->assertOk()
            ->assertJsonStructure(['planned' => ['flights', 'stay', 'food', 'transport', 'activities', 'visa', 'insurance', 'shopping', 'emergency'], 'total']);
    }

    public function test_store_saves_budget_with_planned_costs(): void
    {
        $this->postJson('/api/budgets', $this->payload())
            ->assertCreated()
            ->assertJsonPath('title', 'Paris trip')
            ->assertJsonPath('destination.name', 'Paris')
            ->assertJsonCount(9, 'categories')
            ->assertJsonPath('spent_total', 0);

        $this->assertDatabaseHas('budgets', ['user_id' => $this->user->id, 'title' => 'Paris trip']);
    }

    public function test_store_rejects_invalid_input(): void
    {
        $this->postJson('/api/budgets', $this->payload(['days' => 0, 'comfort' => 'cheap']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['days', 'comfort']);
    }

    public function test_index_lists_only_my_budgets(): void
    {
        $this->createBudget();

        Sanctum::actingAs(User::factory()->create());
        $this->createBudget(['title' => 'Someone else']);

        $this->getJson('/api/budgets')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Someone else');
    }

    public function test_cannot_view_another_users_budget(): void
    {
        $id = $this->createBudget();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/budgets/{$id}")->assertForbidden();
        $this->deleteJson("/api/budgets/{$id}")->assertForbidden();
        $this->postJson("/api/budgets/{$id}/expenses", ['category' => 'food', 'amount' => 10])->assertForbidden();
    }

    public function test_update_recalculates_the_plan(): void
    {
        $id = $this->createBudget();
        $before = $this->getJson("/api/budgets/{$id}")->json('planned_total');

        $this->putJson("/api/budgets/{$id}", ['comfort' => 'luxury'])
            ->assertOk()
            ->assertJsonPath('comfort', 'luxury');

        $this->assertGreaterThan($before, $this->getJson("/api/budgets/{$id}")->json('planned_total'));
    }

    public function test_adding_an_expense_updates_spending(): void
    {
        $id = $this->createBudget();

        $this->postJson("/api/budgets/{$id}/expenses", [
            'category' => 'food',
            'description' => 'Dinner',
            'amount' => 40.50,
        ])
            ->assertCreated()
            ->assertJsonPath('budget.spent_total', 40.5);

        $this->assertDatabaseHas('expenses', ['budget_id' => $id, 'category' => 'food']);
    }

    public function test_overspending_a_category_raises_an_alert(): void
    {
        $id = $this->createBudget();

        $response = $this->postJson("/api/budgets/{$id}/expenses", ['category' => 'shopping', 'amount' => 5000])
            ->assertCreated();

        $levels = collect($response->json('budget.alerts'))->pluck('level');
        $this->assertTrue($levels->contains('danger'));
    }

    public function test_expense_validation(): void
    {
        $id = $this->createBudget();

        $this->postJson("/api/budgets/{$id}/expenses", ['category' => 'pets', 'amount' => -5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category', 'amount']);
    }

    public function test_deleting_an_expense_updates_spending(): void
    {
        $id = $this->createBudget();
        $expenseId = $this->postJson("/api/budgets/{$id}/expenses", ['category' => 'food', 'amount' => 20])
            ->json('expense.id');

        $this->deleteJson("/api/budgets/{$id}/expenses/{$expenseId}")
            ->assertOk()
            ->assertJsonPath('spent_total', 0);
    }

    public function test_suggestions_need_a_limit_or_target(): void
    {
        $id = $this->createBudget(['limit_amount' => null]);

        $this->getJson("/api/budgets/{$id}/suggestions")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target']);
    }

    public function test_suggestions_propose_cheaper_swaps_for_a_low_target(): void
    {
        $id = $this->createBudget(['comfort' => 'luxury']);
        $planned = $this->getJson("/api/budgets/{$id}")->json('planned_total');

        $this->getJson('/api/budgets/'.$id.'/suggestions?target='.round($planned * 0.8))
            ->assertOk()
            ->assertJsonPath('achievable', true)
            ->assertJson(fn ($json) => $json->has('swaps')->where('savings', fn ($savings) => $savings > 0)->etc());
    }

    public function test_deleting_a_budget_removes_its_expenses(): void
    {
        $id = $this->createBudget();
        $this->postJson("/api/budgets/{$id}/expenses", ['category' => 'food', 'amount' => 20]);

        $this->deleteJson("/api/budgets/{$id}")->assertOk();

        $this->assertDatabaseMissing('budgets', ['id' => $id]);
        $this->assertDatabaseMissing('expenses', ['budget_id' => $id]);
    }
}
