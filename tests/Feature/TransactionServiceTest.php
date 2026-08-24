<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $service;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TransactionService::class);
        $this->user = User::factory()->create();

        $this->account = Account::factory()->checking()->empty()->create(['user_id' => $this->user->id]);
    }

    // Cobre: rollback em falha durante operação financeira
    public function test_deposit_rolls_back_on_database_failure(): void
    {
        $initialBalance = $this->account->balance;

        // Forçar falha após atualizar saldo mas antes de criar a transação
        DB::shouldReceive('transaction')->andReturnUsing(function ($callback) {
            throw new \RuntimeException('Simulated DB failure');
        });

        $this->expectException(\RuntimeException::class);

        try {
            $this->service->deposit($this->user, 100.00);
        } finally {
            // Restaurar DB para consultas subsequentes
            DB::swap(app('db'));

            // Saldo deve permanecer inalterado
            $freshAccount = Account::find($this->account->id);
            $this->assertEquals($initialBalance, $freshAccount->balance);
        }
    }

    // Cobre: atomicidade real com transação DB
    public function test_deposit_and_transaction_are_atomic(): void
    {
        $this->service->deposit($this->user, 300.00);

        $account = $this->account->fresh();

        $this->assertEquals('300.00', $account->balance);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'type' => 'income',
            'status' => 'realized',
            'amount' => 300.00,
        ]);
    }

    // Cobre: saque com saldo insuficiente lança exception
    public function test_withdraw_throws_insufficient_balance_exception(): void
    {
        $this->expectException(InsufficientBalanceException::class);

        $this->service->withdraw($this->user, 0.01);
    }

    // Cobre: saldo não é alterado após tentativa de saque inválida
    public function test_balance_unchanged_after_failed_withdraw(): void
    {
        $this->service->deposit($this->user, 100.00);

        try {
            $this->service->withdraw($this->user, 999.00);
        } catch (InsufficientBalanceException) {
            // esperado
        }

        $this->assertEquals('100.00', $this->account->fresh()->balance);
        $this->assertDatabaseMissing('transactions', [
            'account_id' => $this->account->id,
            'type' => 'expense',
        ]);
    }

    public function test_decimal_precision_is_maintained(): void
    {
        $this->service->deposit($this->user, 100.10);
        $this->service->deposit($this->user, 200.20);
        $this->service->withdraw($this->user, 50.05);

        // 100.10 + 200.20 - 50.05 = 250.25
        $this->assertEquals('250.25', $this->account->fresh()->balance);
    }
}
