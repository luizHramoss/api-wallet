<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user  = User::factory()->create();
        Wallet::factory()->empty()->create(['user_id' => $this->user->id]);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    // ─── Saldo ─────────────────────────────────────────────────────────────

    public function test_user_can_view_wallet_balance(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/wallet')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'balance', 'updated_at']])
            ->assertJsonPath('data.balance', 0.0);
    }

    public function test_unauthenticated_user_cannot_view_wallet(): void
    {
        $this->getJson('/api/wallet')->assertStatus(401);
    }

    // ─── Depósito ──────────────────────────────────────────────────────────

    /** @test Cobre: depósito com sucesso */
    public function test_deposit_increases_wallet_balance(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', ['amount' => 200.50])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'credit')
            ->assertJsonPath('data.amount', 200.50)
            ->assertJsonPath('data.balance_after', 200.50);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $this->user->id,
            'balance' => 200.50,
        ]);
    }

    public function test_multiple_deposits_accumulate_correctly(): void
    {
        $this->withToken($this->token)->postJson('/api/wallet/deposit', ['amount' => 100.00]);
        $this->withToken($this->token)->postJson('/api/wallet/deposit', ['amount' => 50.25]);

        $wallet = $this->user->wallet->fresh();
        $this->assertEquals('150.25', $wallet->balance);
    }

    public function test_deposit_fails_with_zero_amount(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', ['amount' => 0])
            ->assertStatus(422);
    }

    public function test_deposit_fails_with_negative_amount(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', ['amount' => -50])
            ->assertStatus(422);
    }

    public function test_deposit_fails_with_more_than_two_decimal_places(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', ['amount' => 10.999])
            ->assertStatus(422);
    }

    public function test_deposit_creates_transaction_record(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/wallet/deposit', ['amount' => 300.00]);

        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $this->user->wallet->id,
            'type'      => 'credit',
            'amount'    => 300.00,
        ]);
    }

    // ─── Saque ─────────────────────────────────────────────────────────────

    /** @test Cobre: atualização correta do saldo após saque */
    public function test_withdraw_decreases_wallet_balance(): void
    {
        // Primeiro deposita
        $this->withToken($this->token)->postJson('/api/wallet/deposit', ['amount' => 500.00]);

        // Depois saca
        $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', ['amount' => 150.00])
            ->assertStatus(200)
            ->assertJsonPath('data.type', 'debit')
            ->assertJsonPath('data.amount', 150.0)
            ->assertJsonPath('data.balance_after', 350.0);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $this->user->id,
            'balance' => 350.00,
        ]);
    }

    /** @test Cobre: saque com saldo insuficiente */
    public function test_withdraw_fails_when_balance_is_insufficient(): void
    {
        // Carteira começa vazia (saldo = 0)
        $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', ['amount' => 100.00])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // Saldo não deve ter sido alterado
        $this->assertEquals('0.00', $this->user->wallet->fresh()->balance);
    }

    public function test_withdraw_fails_when_amount_exceeds_balance(): void
    {
        $this->withToken($this->token)->postJson('/api/wallet/deposit', ['amount' => 100.00]);

        $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', ['amount' => 100.01])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_withdraw_minimum_amount_of_one_cent(): void
    {
        $this->withToken($this->token)->postJson('/api/wallet/deposit', ['amount' => 1.00]);

        $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', ['amount' => 0.01])
            ->assertStatus(200);

        $this->assertEquals('0.99', $this->user->wallet->fresh()->balance);
    }

    public function test_withdraw_below_minimum_fails(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/wallet/withdraw', ['amount' => 0.001])
            ->assertStatus(422);
    }

    public function test_withdraw_creates_debit_transaction(): void
    {
        $this->withToken($this->token)->postJson('/api/wallet/deposit', ['amount' => 200.00]);
        $this->withToken($this->token)->postJson('/api/wallet/withdraw', ['amount' => 75.00]);

        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $this->user->wallet->id,
            'type'      => 'debit',
            'amount'    => 75.00,
        ]);
    }

    // ─── Dashboard ─────────────────────────────────────────────────────────

    public function test_dashboard_returns_correct_structure(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/wallet/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'balance',
                    'last_transactions',
                    'monthly_summary' => [
                        'total_deposited',
                        'total_withdrawn',
                        'period' => ['from', 'to'],
                    ],
                ],
            ]);
    }

    public function test_dashboard_monthly_totals_are_correct(): void
    {
        $this->withToken($this->token)->postJson('/api/wallet/deposit',  ['amount' => 500.00]);
        $this->withToken($this->token)->postJson('/api/wallet/deposit',  ['amount' => 300.00]);
        $this->withToken($this->token)->postJson('/api/wallet/withdraw', ['amount' => 200.00]);

        $response = $this->withToken($this->token)
            ->getJson('/api/wallet/dashboard')
            ->assertStatus(200);

        $this->assertEquals(800.00, $response->json('data.monthly_summary.total_deposited'));
        $this->assertEquals(200.00, $response->json('data.monthly_summary.total_withdrawn'));
        $this->assertEquals(600.00, $response->json('data.balance'));
    }

    public function test_dashboard_shows_at_most_five_transactions(): void
    {
        $this->withToken($this->token)->postJson('/api/wallet/deposit', ['amount' => 1000.00]);

        // Criar 8 saques
        for ($i = 0; $i < 8; $i++) {
            $this->withToken($this->token)->postJson('/api/wallet/withdraw', ['amount' => 10.00]);
        }

        $response = $this->withToken($this->token)->getJson('/api/wallet/dashboard');
        $this->assertCount(5, $response->json('data.last_transactions'));
    }
}
