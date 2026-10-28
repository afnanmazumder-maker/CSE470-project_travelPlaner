<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function validRegistration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_register_returns_user_and_token(): void
    {
        $this->postJson('/api/register', $this->validRegistration())
            ->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_password_is_not_stored_in_plain_text(): void
    {
        $this->postJson('/api/register', $this->validRegistration());

        $this->assertNotSame('password123', User::first()->password);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->postJson('/api/register', $this->validRegistration());

        $this->postJson('/api/register', $this->validRegistration())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->postJson('/api/register', $this->validRegistration([
            'password_confirmation' => 'something-else',
        ]))->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_login_with_correct_credentials(): void
    {
        $this->postJson('/api/register', $this->validRegistration());

        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['user', 'token']);
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $this->postJson('/api/register', $this->validRegistration());

        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_user_endpoint_requires_a_token(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_user_endpoint_returns_the_logged_in_user(): void
    {
        $token = $this->postJson('/api/register', $this->validRegistration())
            ->json('token');

        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('email', 'test@example.com');
    }
}