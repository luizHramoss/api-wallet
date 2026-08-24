<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_user_can_list_own_accounts(): void
    {
        Account::factory()->count(2)->create(['user_id' => $this->user->id]);
        Account::factory()->create(); // outro usuário

        $response = $this->withToken($this->token)
            ->getJson('/api/accounts')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'type', 'balance', 'color', 'is_archived', 'updated_at']],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_unauthenticated_user_cannot_list_accounts(): void
    {
        $this->getJson('/api/accounts')->assertStatus(401);
    }

    public function test_user_can_create_account(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/accounts', ['name' => 'Nubank', 'type' => 'checking', 'color' => '#8A05BE'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Nubank')
            ->assertJsonPath('data.type', 'checking')
            ->assertJsonPath('data.balance', 0);

        $this->assertDatabaseHas('accounts', ['user_id' => $this->user->id, 'name' => 'Nubank']);
    }

    public function test_create_account_requires_valid_type(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/accounts', ['name' => 'X', 'type' => 'invalid'])
            ->assertStatus(422);
    }

    public function test_user_can_update_own_account(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id, 'name' => 'Antigo']);

        $this->withToken($this->token)
            ->patchJson("/api/accounts/{$account->id}", ['name' => 'Novo Nome'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Novo Nome');
    }

    public function test_user_cannot_update_another_users_account(): void
    {
        $other = Account::factory()->create();

        $this->withToken($this->token)
            ->patchJson("/api/accounts/{$other->id}", ['name' => 'Hackeado'])
            ->assertStatus(404);
    }

    public function test_user_can_archive_own_account(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id]);

        $this->withToken($this->token)
            ->deleteJson("/api/accounts/{$account->id}")
            ->assertStatus(200);

        $this->assertTrue($account->fresh()->is_archived);
    }

    public function test_user_cannot_archive_another_users_account(): void
    {
        $other = Account::factory()->create();

        $this->withToken($this->token)
            ->deleteJson("/api/accounts/{$other->id}")
            ->assertStatus(404);

        $this->assertFalse($other->fresh()->is_archived);
    }
}
