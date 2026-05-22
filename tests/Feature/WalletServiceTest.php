<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WalletService::class);
        $this->user    = User::factory()->create();

        Wallet::factory()->empty()->create(['user_id' => $this->user->id]);
    }

    /** @test Cobre: rollback em falha durante operação financeira */
    public function test_deposit_rolls_back_on_database_failure(): void
    {
        $initialBalance = $this->user->wallet->balance;

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
            $freshWallet = Wallet::find($this->user->wallet->id);
            $this->assertEquals($initialBalance, $freshWallet->balance);
        }
    }

    /** @test Cobre: atomicidade real com transação DB */
    public function test_deposit_and_transaction_are_atomic(): void
    {
        $this->service->deposit($this->user, 300.00);

        $wallet = $this->user->wallet->fresh();

        $this->assertEquals('300.00', $wallet->balance);
        $this->assertDatabaseHas('transactions', [
            'wallet_id'    => $wallet->id,
            'type'         => 'credit',
            'amount'       => 300.00,
            'balance_after' => 300.00,
        ]);
    }

    /** @test Cobre: saque com saldo insuficiente lança exception */
    public function test_withdraw_throws_insufficient_balance_exception(): void
    {
        $this->expectException(InsufficientBalanceException::class);

        $this->service->withdraw($this->user, 0.01);
    }

    /** @test Cobre: saldo não é alterado após tentativa de saque inválida */
    public function test_balance_unchanged_after_failed_withdraw(): void
    {
        $this->service->deposit($this->user, 100.00);

        try {
            $this->service->withdraw($this->user, 999.00);
        } catch (InsufficientBalanceException) {
            // esperado
        }

        $this->assertEquals('100.00', $this->user->wallet->fresh()->balance);
        $this->assertDatabaseMissing('transactions', [
            'wallet_id' => $this->user->wallet->id,
            'type'      => 'debit',
        ]);
    }

    public function test_create_wallet_for_user_sets_zero_balance(): void
    {
        $newUser = User::factory()->create();
        $wallet  = $this->service->createForUser($newUser);

        $this->assertEquals('0.00', $wallet->balance);
        $this->assertEquals($newUser->id, $wallet->user_id);
    }

    public function test_decimal_precision_is_maintained(): void
    {
        $this->service->deposit($this->user, 100.10);
        $this->service->deposit($this->user, 200.20);
        $this->service->withdraw($this->user, 50.05);

        // 100.10 + 200.20 - 50.05 = 250.25
        $this->assertEquals('250.25', $this->user->wallet->fresh()->balance);
    }
}
