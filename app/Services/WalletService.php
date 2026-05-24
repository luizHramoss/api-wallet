<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidTransactionException;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

class WalletService
{
    /**
     * Cria uma carteira com saldo zero para o usuário recém-registrado.
     */
    public function createForUser(User $user): Wallet
    {
        return $user->wallet()->create(['balance' => 0.00]);
    }

    /**
     * Realiza um depósito atômico na carteira do usuário.
     *
     * @throws InvalidTransactionException|Throwable
     */
    public function deposit(User $user, float $amount): Transaction
    {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($user, $amount) {
            /** @var Wallet $wallet */
            $wallet = Wallet::lockForUpdate()->where('user_id', $user->id)->firstOrFail();

            $wallet->balance = round(
                (float) $wallet->balance + (float) $amount,
                2
            );
            $wallet->save();

            return $this->recordTransaction($wallet, 'credit', $amount);
        });
    }

    /**
     * Realiza um saque atômico da carteira do usuário.
     *
     * @throws InsufficientBalanceException|InvalidTransactionException|Throwable
     */
    public function withdraw(User $user, float $amount): Transaction
    {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($user, $amount) {
            /** @var Wallet $wallet */
            $wallet = Wallet::lockForUpdate()->where('user_id', $user->id)->firstOrFail();

            if (!$wallet->hasSufficientBalance($amount)) {
                throw new InsufficientBalanceException(
                    "Saldo insuficiente. Saldo disponível: R$ " . number_format($wallet->balance, 2, ',', '.')
                );
            }

            $wallet->balance = round(
                (float) $wallet->balance - (float) $amount,
                2
            );
            $wallet->save();

            return $this->recordTransaction($wallet, 'debit', $amount);
        });
    }

    /**
     * Retorna o histórico de transações paginado com filtros.
     */
    public function getTransactions(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Transaction::query()
            ->whereHas('wallet', fn($q) => $q->where('user_id', $user->id))
            ->orderByDesc('created_at');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->paginate($perPage);
    }

    /**
     * Retorna os dados do dashboard: saldo, últimas transações, totais do mês.
     */
    public function getDashboard(User $user): array
    {
        $wallet = $user->wallet;

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth   = Carbon::now()->endOfMonth();

        $monthTransactions = Transaction::query()
            ->where('wallet_id', $wallet->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);

        $totalDeposited = (clone $monthTransactions)
            ->where('type', 'credit')
            ->sum('amount');

        $totalWithdrawn = (clone $monthTransactions)
            ->where('type', 'debit')
            ->sum('amount');

        $lastTransactions = Transaction::query()
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'balance'          => $wallet->balance,
            'last_transactions' => $lastTransactions,
            'monthly_summary'  => [
                'total_deposited' => round((float) $totalDeposited, 2),
                'total_withdrawn' => round((float) $totalWithdrawn, 2),
                'period'          => [
                    'from' => $startOfMonth->toDateString(),
                    'to'   => $endOfMonth->toDateString(),
                ],
            ],
        ];
    }

    /**
     * Persiste o registro da transação na base de dados.
     */
    private function recordTransaction(Wallet $wallet, string $type, float $amount): Transaction
    {
        return Transaction::create([
            'wallet_id'    => $wallet->id,
            'type'         => $type,
            'amount'       => $amount,
            'balance_after' => $wallet->balance,
            'description'  => $type === 'credit' ? 'Depósito' : 'Saque',
        ]);
    }

    /**
     * Garante que o valor é positivo e maior que R$ 0,01.
     *
     * @throws InvalidTransactionException
     */
    private function assertPositiveAmount(float $amount): void
    {
        if ($amount < 0.01) {
            throw new InvalidTransactionException('O valor mínimo para operações é R$ 0,01.');
        }
    }
}
