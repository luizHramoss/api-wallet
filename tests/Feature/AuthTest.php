<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ─── Register ──────────────────────────────────────────────────────────

    public function test_user_can_register_successfully(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'João Silva',
            'email'                 => 'joao@email.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success', 'message',
                'data' => ['user' => ['id', 'name', 'email'], 'token'],
            ])
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', ['email' => 'joao@email.com']);
        $this->assertDatabaseHas('wallets', []);
    }

    public function test_wallet_is_created_with_zero_balance_on_register(): void
    {
        $this->postJson('/api/auth/register', [
            'name'                  => 'Maria',
            'email'                 => 'maria@email.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'maria@email.com')->first();
        $this->assertNotNull($user->wallet);
        $this->assertEquals('0.00', $user->wallet->balance);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@email.com']);

        $this->postJson('/api/auth/register', [
            'name'                  => 'Outro',
            'email'                 => 'dup@email.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)
          ->assertJsonPath('success', false);
    }

    public function test_register_fails_with_short_password(): void
    {
        $this->postJson('/api/auth/register', [
            'name'                  => 'Teste',
            'email'                 => 'teste@email.com',
            'password'              => '123',
            'password_confirmation' => '123',
        ])->assertStatus(422);
    }

    // ─── Login ─────────────────────────────────────────────────────────────

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('senha123')]);

        $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'senha123',
        ])->assertStatus(200)
          ->assertJsonStructure(['data' => ['token']])
          ->assertJsonPath('success', true);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correta')]);

        $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'errada',
        ])->assertStatus(401)
          ->assertJsonPath('success', false);
    }

    // ─── Logout ────────────────────────────────────────────────────────────

    public function test_user_can_logout(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Token deve estar inválido após logout
        $this->withToken($token)
            ->getJson('/api/wallet')
            ->assertStatus(401);
    }
}
