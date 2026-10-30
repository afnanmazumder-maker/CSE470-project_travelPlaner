<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreferenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferences_require_login(): void
    {
        $this->getJson('/api/preferences')->assertUnauthorized();
    }

    public function test_new_user_has_empty_preferences(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/preferences')
            ->assertOk()
            ->assertJsonPath('onboarded', false)
            ->assertJsonPath('pace', 'moderate');
    }

    public function test_quiz_saves_interests_pace_and_budget(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/preferences', [
            'interests' => ['history', 'museum'],
            'pace' => 'relaxed',
            'budget_band' => 'luxury',
        ])
            ->assertOk()
            ->assertJsonPath('onboarded', true)
            ->assertJsonPath('pace', 'relaxed')
            ->assertJsonPath('interest_weights.history', 0.7);

        $this->assertDatabaseHas('interest_weights', ['user_id' => $user->id, 'tag' => 'museum']);
    }

    public function test_retaking_the_quiz_replaces_old_interests(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/preferences', ['interests' => ['history'], 'pace' => 'relaxed', 'budget_band' => 'budget']);
        $this->putJson('/api/preferences', ['interests' => ['beach'], 'pace' => 'packed', 'budget_band' => 'budget']);

        $this->assertDatabaseMissing('interest_weights', ['user_id' => $user->id, 'tag' => 'history']);
        $this->assertDatabaseHas('interest_weights', ['user_id' => $user->id, 'tag' => 'beach']);
    }

    public function test_unknown_interest_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/preferences', [
            'interests' => ['histroy'],
            'pace' => 'relaxed',
            'budget_band' => 'budget',
        ])->assertStatus(422)->assertJsonValidationErrors(['interests.0']);
    }
        public function test_skipping_marks_the_user_onboarded_without_interests(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/preferences/skip')
            ->assertOk()
            ->assertJsonPath('onboarded', true)
            ->assertJsonCount(0, 'interest_weights');
    }

    public function test_skipping_keeps_existing_interests(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/preferences', ['interests' => ['history'], 'pace' => 'packed', 'budget_band' => 'budget']);
        $this->postJson('/api/preferences/skip')->assertOk();

        $this->assertDatabaseHas('interest_weights', ['user_id' => $user->id, 'tag' => 'history']);
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'pace' => 'packed']);
    }

    public function test_skip_requires_login(): void
    {
        $this->postJson('/api/preferences/skip')->assertUnauthorized();
    }
}