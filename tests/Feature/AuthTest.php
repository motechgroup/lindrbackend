<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_successfully(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'role' => 'male',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'role', 'status'],
                    'access_token',
                    'token_type',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role' => 'male',
            'status' => 'active',
        ]);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertNotNull($user->wallet);
        $this->assertEquals(0, $user->wallet->coin_balance);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secret123',
            'role' => UserRole::Female,
            'status' => UserStatus::Active,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'jane@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'jane@example.com');
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::factory()->suspended()->create([
            'email' => 'suspended@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'suspended@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_user_can_fetch_own_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Successfully logged out.');

        $this->assertCount(0, $user->tokens);
    }

    public function test_revoked_token_cannot_access_protected_routes_after_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(200);

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        // Attempting to access /auth/me with revoked token returns 401
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(401);
    }

    public function test_user_and_profile_data_are_not_deleted_from_database_on_logout(): void
    {
        $user = User::factory()->create(['email' => 'keepme@lindr.local']);
        $user->wallet()->create(['coin_balance' => 250]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(200);

        // Confirm database records persist
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'keepme@lindr.local']);
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'coin_balance' => 250]);
    }

    public function test_second_google_account_can_authenticate_after_logout(): void
    {
        // First Google User
        $res1 = $this->postJson('/api/v1/auth/google', [
            'google_id' => 'google_user_001',
            'email' => 'user1@gmail.com',
            'name' => 'User One',
        ]);
        $res1->assertStatus(200);
        $token1 = $res1->json('data.token');
        $user1Id = $res1->json('data.user.id');

        // Logout user 1
        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => 'Bearer '.$token1])
            ->assertStatus(200);

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        // Second Google User authenticates
        $res2 = $this->postJson('/api/v1/auth/google', [
            'google_id' => 'google_user_002',
            'email' => 'user2@gmail.com',
            'name' => 'User Two',
        ]);
        $res2->assertStatus(200);
        $token2 = $res2->json('data.token');
        $user2Id = $res2->json('data.user.id');

        $this->assertNotEquals($user1Id, $user2Id);
        $this->assertNotEquals($token1, $token2);

        // Verify User 2 session fetch returns User 2 profile
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$token2])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $user2Id)
            ->assertJsonPath('data.email', 'user2@gmail.com');
    }
}
