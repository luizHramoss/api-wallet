<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private User $userB;
    private string $tokenA;
    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA  = User::factory()->create();
        $this->userB  = User::factory()->create();

        Wallet::factory()->withBalance(1000)->create(['user_id' => $this->userA->id]);
        Wallet::factory()->withBalance(1000)->create(['user_id' => $this->userB->id]);

        $this->tokenA = $this->userA->createToken('test')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test')->plainTextToken;
    }

    // ─── Listagem básica ───────────────────────────────────────────────────

    public function test_user_can_list_own_transactions(): void
    {
        // Gerar transações para userA
        $this->withToken($this->tokenA)->postJson('/api/wallet/deposit',  ['amount' => 100.00]);
        $this->withToken($this->tokenA)->postJson('/api/wallet/withdraw', ['amount' => 50.00]);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/transactions')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'type', 'amount', 'balance_after', 'created_at']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertEquals(2, $response->json('meta.total'));
    }

    /** @test Cobre: usuário não acessa dados de outro usuário */
    public function test_user_cannot_see_other_users_transactions(): void
    {
        // Gerar transações para userB
        $this->withToken($this->tokenB)->postJson('/api/wallet/deposit', ['amount' => 500.00]);
        $this->withToken($this->tokenB)->postJson('/api/wallet/deposit', ['amount' => 200.00]);

        // userA não deve ver nada das transações de userB
        $response = $this->withToken($this->tokenA)
            ->getJson('/api/transactions')
            ->assertStatus(200);

        $this->assertEquals(0, $response->json('meta.total'));
    }

    public function test_unauthenticated_user_cannot_list_transactions(): void
    {
        $this->getJson('/api/transactions')->assertStatus(401);
    }

    // ─── Filtros ───────────────────────────────────────────────────────────

    public function test_filter_by_type_credit(): void
    {
        $this->withToken($this->tokenA)->postJson('/api/wallet/deposit',  ['amount' => 100.00]);
        $this->withToken($this->tokenA)->postJson('/api/wallet/withdraw', ['amount' => 30.00]);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/transactions?type=credit')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('credit', $response->json('data.0.type'));
    }

    public function test_filter_by_type_debit(): void
    {
        $this->withToken($this->tokenA)->postJson('/api/wallet/deposit',  ['amount' => 100.00]);
        $this->withToken($this->tokenA)->postJson('/api/wallet/withdraw', ['amount' => 30.00]);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/transactions?type=debit')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('debit', $response->json('data.0.type'));
    }

    public function test_filter_by_invalid_type_returns_422(): void
    {
        $this->withToken($this->tokenA)
            ->getJson('/api/transactions?type=invalid')
            ->assertStatus(422);
    }

    public function test_filter_by_date_range(): void
    {
        $this->withToken($this->tokenA)->postJson('/api/wallet/deposit', ['amount' => 100.00]);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/transactions?date_from=' . now()->toDateString() . '&date_to=' . now()->toDateString())
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_filter_date_to_cannot_be_before_date_from(): void
    {
        $this->withToken($this->tokenA)
            ->getJson('/api/transactions?date_from=2024-01-31&date_to=2024-01-01')
            ->assertStatus(422);
    }

    // ─── Paginação ─────────────────────────────────────────────────────────

    public function test_pagination_per_page_is_respected(): void
    {
        // Criar 20 transações
        $wallet = $this->userA->wallet;
        Transaction::factory(20)->credit()->create([
            'wallet_id'    => $wallet->id,
            'balance_after' => 100,
        ]);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/transactions?per_page=5')
            ->assertStatus(200);

        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(20, $response->json('meta.total'));
        $this->assertEquals(4,  $response->json('meta.last_page'));
    }

    public function test_per_page_cannot_exceed_100(): void
    {
        $this->withToken($this->tokenA)
            ->getJson('/api/transactions?per_page=200')
            ->assertStatus(422);
    }
}
