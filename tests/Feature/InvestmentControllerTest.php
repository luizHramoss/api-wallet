<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->checking()->withBalance(10000)->create(['user_id' => $this->user->id]);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_user_can_list_own_investments(): void
    {
        Investment::factory()->count(2)->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
        Investment::factory()->create(); // outro usuário

        $response = $this->withToken($this->token)
            ->getJson('/api/investments')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'account_id', 'name', 'symbol', 'type', 'quantity', 'average_price', 'current_price', 'invested_value', 'current_value', 'rentability_percent']],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_can_create_investment(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/investments', ['account_id' => $this->account->id, 'name' => 'Tesouro Selic', 'type' => 'fixed_income'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Tesouro Selic')
            ->assertJsonPath('data.quantity', 0);
    }

    public function test_user_can_record_buy_movement(): void
    {
        $investment = Investment::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);

        $this->withToken($this->token)
            ->postJson("/api/investments/{$investment->id}/movements", ['type' => 'buy', 'quantity' => 10, 'price' => 20])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'buy')
            ->assertJsonPath('data.amount', 200);

        $this->assertEquals('9800.00', $this->account->fresh()->balance);
    }

    public function test_dividend_requires_amount_not_quantity(): void
    {
        $investment = Investment::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);

        $this->withToken($this->token)
            ->postJson("/api/investments/{$investment->id}/movements", ['type' => 'dividend'])
            ->assertStatus(422);

        $this->withToken($this->token)
            ->postJson("/api/investments/{$investment->id}/movements", ['type' => 'dividend', 'amount' => 25])
            ->assertStatus(201);
    }

    public function test_selling_more_than_owned_returns_422(): void
    {
        $investment = Investment::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
        $this->withToken($this->token)->postJson("/api/investments/{$investment->id}/movements", ['type' => 'buy', 'quantity' => 5, 'price' => 10]);

        $this->withToken($this->token)
            ->postJson("/api/investments/{$investment->id}/movements", ['type' => 'sell', 'quantity' => 6, 'price' => 10])
            ->assertStatus(422);
    }

    public function test_user_can_list_movements(): void
    {
        $investment = Investment::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
        $this->withToken($this->token)->postJson("/api/investments/{$investment->id}/movements", ['type' => 'buy', 'quantity' => 5, 'price' => 10]);

        $response = $this->withToken($this->token)
            ->getJson("/api/investments/{$investment->id}/movements")
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_user_can_update_current_price(): void
    {
        $investment = Investment::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);

        $this->withToken($this->token)
            ->patchJson("/api/investments/{$investment->id}", ['current_price' => 42.50])
            ->assertStatus(200)
            ->assertJsonPath('data.current_price', 42.50);
    }

    public function test_user_cannot_access_another_users_investment(): void
    {
        $other = Investment::factory()->create();

        $this->withToken($this->token)
            ->patchJson("/api/investments/{$other->id}", ['current_price' => 10])
            ->assertStatus(404);

        $this->withToken($this->token)
            ->postJson("/api/investments/{$other->id}/movements", ['type' => 'buy', 'quantity' => 1, 'price' => 1])
            ->assertStatus(404);
    }

    public function test_user_can_delete_investment(): void
    {
        $investment = Investment::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);

        $this->withToken($this->token)
            ->deleteJson("/api/investments/{$investment->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('investments', ['id' => $investment->id]);
    }

    public function test_portfolio_summary_endpoint(): void
    {
        $investment = Investment::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
        $this->withToken($this->token)->postJson("/api/investments/{$investment->id}/movements", ['type' => 'buy', 'quantity' => 10, 'price' => 20]);

        $response = $this->withToken($this->token)
            ->getJson('/api/investments/summary')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['total_invested', 'total_current_value', 'rentability_percent', 'invested_this_month', 'dividends_this_month', 'period'],
            ]);

        $this->assertEquals(200.0, $response->json('data.invested_this_month'));
    }
}
