<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_bearer_token(): void
    {
        User::factory()->create([
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'bearer')
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/login', [
            'email' => 'demo@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_dashboard_requires_a_token(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_dashboard_returns_data_for_a_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'demo@example.com',
        ]);

        $token = auth('api')->login($user);

        $this->withToken($token)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('user.email', 'demo@example.com')
            ->assertJsonPath('title', '保護されたページ');
    }

    public function test_logout_invalidates_the_token(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->withToken($token)->getJson('/api/dashboard')->assertUnauthorized();
    }
}
