<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receives_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'maria@example.com')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.com',
        ]);
    }

    public function test_register_with_duplicated_email_returns_validation_error(): void
    {
        User::factory()->create(['email' => 'maria@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_and_receives_token(): void
    {
        User::factory()->create([
            'email' => 'joao@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'joao@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'joao@example.com')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token']);
    }

    public function test_login_with_wrong_password_returns_validation_error(): void
    {
        User::factory()->create([
            'email' => 'joao@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'joao@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_access_me(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
        $this->getJson('/api/auctions')->assertUnauthorized();
        $this->postJson('/api/auctions', [])->assertUnauthorized();
    }

    public function test_logout_removes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;
        $tokenId = (int) explode('|', $token, 2)[0];

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout');

        $response->assertOk()
            ->assertJsonPath('message', 'Logout realizado com sucesso.');

        $this->assertNull(PersonalAccessToken::find($tokenId));
    }
}
