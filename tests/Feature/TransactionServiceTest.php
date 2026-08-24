<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidTransactionException;
use App\Models\Account;
use App\Models\Transaction;
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
    public function test_create_rolls_back_on_database_failure(): void
    {
        $initialBalance = $this->account->balance;

        DB::shouldReceive('transaction')->andReturnUsing(function () {
            throw new \RuntimeException('Simulated DB failure');
        });

        $this->expectException(\RuntimeException::class);

        try {
            $this->service->create($this->user, [
                'account_id' => $this->account->id,
                'type' => Transaction::TYPE_INCOME,
                'amount' => 100.00,
            ]);
        } finally {
            DB::swap(app('db'));

            $freshAccount = Account::find($this->account->id);
            $this->assertEquals($initialBalance, $freshAccount->balance);
        }
    }

    // Cobre: atomicidade real com transação DB
    public function test_income_credits_account_and_is_atomic(): void
    {
        $this->service->create($this->user, [
            'account_id' => $this->account->id,
            'type' => Transaction::TYPE_INCOME,
            'amount' => 300.00,
        ]);

        $account = $this->account->fresh();

        $this->assertEquals('300.00', $account->balance);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'type' => 'income',
            'status' => 'realized',
            'amount' => 300.00,
        ]);
    }

    // Cobre: despesa com saldo insuficiente lança exception
    public function test_expense_throws_insufficient_balance_exception(): void
    {
        $this->expectException(InsufficientBalanceException::class);

        $this->service->create($this->user, [
            'account_id' => $this->account->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 0.01,
        ]);
    }

    // Cobre: saldo não é alterado após tentativa de despesa inválida
    public function test_balance_unchanged_after_failed_expense(): void
    {
        $this->service->create($this->user, [
            'account_id' => $this->account->id,
            'type' => Transaction::TYPE_INCOME,
            'amount' => 100.00,
        ]);

        try {
            $this->service->create($this->user, [
                'account_id' => $this->account->id,
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => 999.00,
            ]);
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
        $this->service->create($this->user, ['account_id' => $this->account->id, 'type' => Transaction::TYPE_INCOME, 'amount' => 100.10]);
        $this->service->create($this->user, ['account_id' => $this->account->id, 'type' => Transaction::TYPE_INCOME, 'amount' => 200.20]);
        $this->service->create($this->user, ['account_id' => $this->account->id, 'type' => Transaction::TYPE_EXPENSE, 'amount' => 50.05]);

        // 100.10 + 200.20 - 50.05 = 250.25
        $this->assertEquals('250.25', $this->account->fresh()->balance);
    }

    // Cobre: planned não mexe no saldo até virar realized
    public function test_planned_transaction_does_not_affect_balance(): void
    {
        $this->service->create($this->user, [
            'account_id' => $this->account->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 50.00,
            'status' => Transaction::STATUS_PLANNED,
        ]);

        $this->assertEquals('0.00', $this->account->fresh()->balance);
    }

    public function test_update_transitioning_planned_to_realized_applies_balance_impact(): void
    {
        $transaction = $this->service->create($this->user, [
            'account_id' => $this->account->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 50.00,
            'status' => Transaction::STATUS_PLANNED,
        ]);
        $this->service->create($this->user, ['account_id' => $this->account->id, 'type' => Transaction::TYPE_INCOME, 'amount' => 100.00]);

        $this->service->update($transaction, ['status' => Transaction::STATUS_REALIZED]);

        $this->assertEquals('50.00', $this->account->fresh()->balance);
    }

    public function test_update_amount_adjusts_balance_by_the_difference(): void
    {
        $this->service->create($this->user, ['account_id' => $this->account->id, 'type' => Transaction::TYPE_INCOME, 'amount' => 200.00]);
        $transaction = $this->service->create($this->user, [
            'account_id' => $this->account->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 50.00,
        ]);

        $this->service->update($transaction, ['amount' => 80.00]);

        // 200 - 80 = 120
        $this->assertEquals('120.00', $this->account->fresh()->balance);
    }

    public function test_delete_reverses_balance_impact(): void
    {
        $transaction = $this->service->create($this->user, [
            'account_id' => $this->account->id,
            'type' => Transaction::TYPE_INCOME,
            'amount' => 100.00,
        ]);

        $this->service->delete($transaction);

        $this->assertEquals('0.00', $this->account->fresh()->balance);
        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    public function test_delete_planned_transaction_has_no_balance_impact(): void
    {
        $transaction = $this->service->create($this->user, [
            'account_id' => $this->account->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 100.00,
            'status' => Transaction::STATUS_PLANNED,
        ]);

        $this->service->delete($transaction);

        $this->assertEquals('0.00', $this->account->fresh()->balance);
    }

    public function test_transfer_creates_two_linked_transactions_and_moves_balance(): void
    {
        $this->service->create($this->user, ['account_id' => $this->account->id, 'type' => Transaction::TYPE_INCOME, 'amount' => 500.00]);
        $destination = Account::factory()->checking()->empty()->create(['user_id' => $this->user->id]);

        $outLeg = $this->service->create($this->user, [
            'account_id' => $this->account->id,
            'to_account_id' => $destination->id,
            'type' => Transaction::TYPE_TRANSFER,
            'amount' => 150.00,
        ]);

        $this->assertEquals('350.00', $this->account->fresh()->balance);
        $this->assertEquals('150.00', $destination->fresh()->balance);
        $this->assertEquals('out', $outLeg->transfer_direction);
        $this->assertNotNull($outLeg->transfer_pair_id);

        $inLeg = Transaction::find($outLeg->transfer_pair_id);
        $this->assertEquals($destination->id, $inLeg->account_id);
        $this->assertEquals('in', $inLeg->transfer_direction);
        $this->assertEquals($outLeg->id, $inLeg->transfer_pair_id);
    }

    public function test_transfer_to_same_account_is_rejected(): void
    {
        $this->expectException(InvalidTransactionException::class);

        $this->service->create($this->user, [
            'account_id' => $this->account->id,
            'to_account_id' => $this->account->id,
            'type' => Transaction::TYPE_TRANSFER,
            'amount' => 50.00,
        ]);
    }

    public function test_deleting_a_transfer_reverses_both_legs(): void
    {
        $this->service->create($this->user, ['account_id' => $this->account->id, 'type' => Transaction::TYPE_INCOME, 'amount' => 500.00]);
        $destination = Account::factory()->checking()->empty()->create(['user_id' => $this->user->id]);

        $outLeg = $this->service->create($this->user, [
            'account_id' => $this->account->id,
            'to_account_id' => $destination->id,
            'type' => Transaction::TYPE_TRANSFER,
            'amount' => 150.00,
        ]);

        $this->service->delete($outLeg);

        $this->assertEquals('500.00', $this->account->fresh()->balance);
        $this->assertEquals('0.00', $destination->fresh()->balance);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_transfer_cannot_be_updated(): void
    {
        $this->service->create($this->user, ['account_id' => $this->account->id, 'type' => Transaction::TYPE_INCOME, 'amount' => 500.00]);
        $destination = Account::factory()->checking()->empty()->create(['user_id' => $this->user->id]);

        $outLeg = $this->service->create($this->user, [
            'account_id' => $this->account->id,
            'to_account_id' => $destination->id,
            'type' => Transaction::TYPE_TRANSFER,
            'amount' => 150.00,
        ]);

        $this->expectException(InvalidTransactionException::class);

        $this->service->update($outLeg, ['amount' => 200.00]);
    }
}
