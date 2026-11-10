<?php

namespace Tests\Feature;

use App\Models\Phrase;
use App\Models\User;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhraseApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LanguageSeeder::class);
    }

    public function test_lists_ten_languages_with_sixteen_phrases_each(): void
    {
        $languages = $this->getJson('/api/phrases/languages')
            ->assertOk()
            ->assertJsonCount(10, 'languages')
            ->assertJsonPath('categories', ['greetings', 'food', 'directions', 'emergencies'])
            ->assertJsonPath('machine_translation', false)
            ->json('languages');

        $this->assertSame([16], array_values(array_unique(array_column($languages, 'phrase_count'))));
    }

    public function test_phrases_are_grouped_by_category_with_pronunciation(): void
    {
        $groups = $this->getJson('/api/phrases?language=ja')->assertOk()->json();

        $this->assertSame(['greetings', 'food', 'directions', 'emergencies'], array_keys($groups));
        $this->assertSame('すみません', $groups['greetings'][2]['translation']);
        $this->assertSame('sumimasen', $groups['greetings'][2]['romanization']);
        $this->assertNotEmpty($groups['greetings'][2]['pronunciation']);
    }

    public function test_search_matches_the_english_phrase(): void
    {
        $groups = $this->getJson('/api/phrases?language=id&q=hospital')->assertOk()->json();

        $this->assertSame(['Di mana rumah sakit?'], array_column($groups['emergencies'], 'translation'));
    }

    public function test_unknown_language_or_category_is_rejected(): void
    {
        $this->getJson('/api/phrases?language=xx')->assertUnprocessable()->assertJsonValidationErrors('language');
        $this->getJson('/api/phrases?language=ja&category=shopping')->assertUnprocessable()->assertJsonValidationErrors('category');
    }

    public function test_favorites_require_login(): void
    {
        $this->getJson('/api/phrases/favorites')->assertUnauthorized();
    }

    public function test_favoriting_twice_keeps_one_and_is_private_to_the_user(): void
    {
        $phrase = Phrase::where('language_code', 'th')->where('english', 'Help!')->firstOrFail();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/phrases/{$phrase->id}/favorite")->assertOk();
        $this->postJson("/api/phrases/{$phrase->id}/favorite")->assertOk();

        $this->getJson('/api/phrases/favorites')->assertOk()->assertJsonCount(1)->assertJsonPath('0.translation', 'ช่วยด้วย!');
        $emergencies = $this->getJson('/api/phrases?language=th&category=emergencies')->json('emergencies');
        $this->assertTrue(collect($emergencies)->firstWhere('id', $phrase->id)['is_favorite']);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/phrases/favorites')->assertOk()->assertJsonCount(0);

        Sanctum::actingAs($user);
        $this->deleteJson("/api/phrases/{$phrase->id}/favorite")->assertOk()->assertJsonPath('is_favorite', false);
        $this->getJson('/api/phrases/favorites')->assertJsonCount(0);
    }

    public function test_tips_for_a_destination_country_cover_four_topics(): void
    {
        $this->getJson('/api/cultural-tips/countries')->assertOk()->assertJsonCount(10);

        $topics = $this->getJson('/api/cultural-tips?country=Indonesia')
            ->assertOk()
            ->assertJsonPath('language_code', 'id')
            ->json('topics');

        $this->assertSame(['tipping', 'dress_code', 'customs', 'business_hours'], array_column($topics, 'key'));
        $this->assertCount(1, $topics[0]['tips']);
    }

    public function test_unknown_country_is_rejected(): void
    {
        $this->getJson('/api/cultural-tips?country=Atlantis')->assertUnprocessable()->assertJsonValidationErrors('country');
    }
}
