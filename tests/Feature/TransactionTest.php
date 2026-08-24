<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\RecurringBill;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private User $userB;

    private Account $accountA;

    private string $tokenA;

    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create();
        $this->userB = User::factory()->create();

        $this->accountA = Account::factory()->checking()->withBalance(1000)->create(['user_id' => $this->userA->id]);
        Account::factory()->checking()->withBalance(1000)->create(['user_id' => $this->userB->id]);

        $this->tokenA = $this->userA->createToken('test')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test')->plainTextToken;
    }

    private function income(float $amount = 100.00): TestResponse
    {
        return $this->withToken($this->tokenA)->postJson('/api/transactions', [
            'account_id' => $this->accountA->id,
            'type' => 'income',
            'amount' => $amount,
        ]);
    }

    private function expense(float $amount = 30.00): TestResponse
    {
        return $this->withToken($this->tokenA)->postJson('/api/transactions', [
            'account_id' => $this->accountA->id,
            'type' => 'expense',
            'amount' => $amount,
        ]);
    }

    // ─── Criação ───────────────────────────────────────────────────────────

    public function test_user_can_create_income_transaction(): void
    {
        $this->income(200.50)
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'income')
            ->assertJsonPath('data.status', 'realized')
            ->assertJsonPath('data.amount', 200.50);

        $this->assertEquals('1200.50', $this->accountA->fresh()->balance);
    }

    public function test_user_can_create_expense_transaction(): void
    {
        $this->expense(150.00)
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'expense');

        $this->assertEquals('850.00', $this->accountA->fresh()->balance);
    }

    public function test_expense_fails_with_insufficient_balance(): void
    {
        $this->withToken($this->tokenA)->postJson('/api/transactions', [
            'account_id' => $this->accountA->id, 'type' => 'expense', 'amount' => 5000.00,
        ])->assertStatus(422);
    }

    public function test_user_cannot_create_transaction_on_another_users_account(): void
    {
        $foreignAccount = $this->userB->accounts()->first();

        $this->withToken($this->tokenA)->postJson('/api/transactions', [
            'account_id' => $foreignAccount->id, 'type' => 'income', 'amount' => 100.00,
        ])->assertStatus(422);
    }

    public function test_transfer_between_own_accounts(): void
    {
        $secondAccount = Account::factory()->checking()->empty()->create(['user_id' => $this->userA->id]);

        $this->withToken($this->tokenA)->postJson('/api/transactions', [
            'account_id' => $this->accountA->id,
            'to_account_id' => $secondAccount->id,
            'type' => 'transfer',
            'amount' => 300.00,
        ])->assertStatus(201)->assertJsonPath('data.transfer_direction', 'out');

        $this->assertEquals('700.00', $this->accountA->fresh()->balance);
        $this->assertEquals('300.00', $secondAccount->fresh()->balance);
    }

    public function test_transfer_requires_to_account_id(): void
    {
        $this->withToken($this->tokenA)->postJson('/api/transactions', [
            'account_id' => $this->accountA->id, 'type' => 'transfer', 'amount' => 100.00,
        ])->assertStatus(422);
    }

    // ─── Atualização e exclusão ────────────────────────────────────────────

    public function test_user_can_update_own_transaction(): void
    {
        $id = $this->income(100.00)->json('data.id');

        $this->withToken($this->tokenA)
            ->patchJson("/api/transactions/{$id}", ['description' => 'Atualizado'])
            ->assertStatus(200)
            ->assertJsonPath('data.description', 'Atualizado');
    }

    public function test_user_cannot_update_another_users_transaction(): void
    {
        $id = $this->income(100.00)->json('data.id');

        auth()->forgetGuards();

        $this->withToken($this->tokenB)
            ->patchJson("/api/transactions/{$id}", ['description' => 'Hackeado'])
            ->assertStatus(404);
    }

    public function test_user_can_delete_own_transaction_and_balance_reverts(): void
    {
        $id = $this->income(100.00)->json('data.id');

        $this->withToken($this->tokenA)
            ->deleteJson("/api/transactions/{$id}")
            ->assertStatus(200);

        $this->assertEquals('1000.00', $this->accountA->fresh()->balance);
    }

    // ─── Listagem básica ───────────────────────────────────────────────────

    public function test_user_can_list_own_transactions(): void
    {
        $this->income(100.00);
        $this->expense(50.00);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/transactions')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'account_id', 'type', 'status', 'amount', 'occurred_at', 'created_at']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertEquals(2, $response->json('meta.total'));
    }

    // Cobre: usuário não acessa dados de outro usuário
    public function test_user_cannot_see_other_users_transactions(): void
    {
        $foreignAccount = $this->userB->accounts()->first();
        $this->withToken($this->tokenB)->postJson('/api/transactions', [
            'account_id' => $foreignAccount->id, 'type' => 'income', 'amount' => 500.00,
        ]);

        // Sanctum's guard memoizes the resolved user for its lifetime in the
        // container - switching the Bearer token alone isn't enough within
        // the same test, or the previous request's user gets reused (see
        // the same workaround in AuthTest::test_user_can_logout).
        auth()->forgetGuards();

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

    public function test_filter_by_type_income(): void
    {
        $this->income(100.00);
        $this->expense(30.00);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/transactions?type=income')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('income', $response->json('data.0.type'));
    }

    public function test_filter_by_type_expense(): void
    {
        $this->income(100.00);
        $this->expense(30.00);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/transactions?type=expense')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('expense', $response->json('data.0.type'));
    }

    public function test_filter_by_invalid_type_returns_422(): void
    {
        $this->withToken($this->tokenA)
            ->getJson('/api/transactions?type=invalid')
            ->assertStatus(422);
    }

    public function test_filter_by_status_realized(): void
    {
        $this->income(100.00);
        Transaction::factory()->planned()->create(['account_id' => $this->accountA->id]);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/transactions?status=realized')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('realized', $response->json('data.0.status'));
    }

    public function test_filter_by_is_recurring(): void
    {
        $bill = RecurringBill::factory()->create(['user_id' => $this->userA->id, 'account_id' => $this->accountA->id]);
        Transaction::factory()->planned()->create(['account_id' => $this->accountA->id, 'recurring_bill_id' => $bill->id]);
        $this->income(100.00);

        $recurring = $this->withToken($this->tokenA)->getJson('/api/transactions?is_recurring=true')->assertStatus(200);
        $this->assertEquals(1, $recurring->json('meta.total'));

        $notRecurring = $this->withToken($this->tokenA)->getJson('/api/transactions?is_recurring=false')->assertStatus(200);
        $this->assertEquals(1, $notRecurring->json('meta.total'));
        $this->assertEquals('income', $notRecurring->json('data.0.type'));
    }

    public function test_filter_by_account_id(): void
    {
        $secondAccount = Account::factory()->checking()->empty()->create(['user_id' => $this->userA->id]);
        $this->income(100.00);
        $this->withToken($this->tokenA)->postJson('/api/transactions', [
            'account_id' => $secondAccount->id, 'type' => 'income', 'amount' => 50.00,
        ]);

        $response = $this->withToken($this->tokenA)
            ->getJson("/api/transactions?account_id={$secondAccount->id}")
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_filter_by_date_range(): void
    {
        $this->income(100.00);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/transactions?date_from='.now()->toDateString().'&date_to='.now()->toDateString())
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
        Transaction::factory(20)->credit()->create(['account_id' => $this->accountA->id]);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/transactions?per_page=5')
            ->assertStatus(200);

        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(20, $response->json('meta.total'));
        $this->assertEquals(4, $response->json('meta.last_page'));
    }

    public function test_per_page_cannot_exceed_100(): void
    {
        $this->withToken($this->tokenA)
            ->getJson('/api/transactions?per_page=200')
            ->assertStatus(422);
    }
}
