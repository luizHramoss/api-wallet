<?php

namespace App\Services;

use App\Exceptions\InvalidTransactionException;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

class TransactionService
{
    public function __construct(private readonly AccountService $accountService) {}

    /**
     * Realiza um depósito atômico na conta padrão do usuário.
     *
     * @throws InvalidTransactionException|Throwable
     */
    public function deposit(User $user, float $amount, ?string $description = null): Transaction
    {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($user, $amount, $description) {
            $account = $this->accountService->lockDefaultAccountFor($user);
            $this->accountService->credit($account, $amount);

            return $this->recordTransaction($account->id, Transaction::TYPE_INCOME, $amount, $description ?? 'Depósito');
        });
    }

    /**
     * Realiza um saque atômico da conta padrão do usuário.
     *
     * @throws InvalidTransactionException|Throwable
     */
    public function withdraw(User $user, float $amount, ?string $description = null): Transaction
    {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($user, $amount, $description) {
            $account = $this->accountService->lockDefaultAccountFor($user);
            $this->accountService->debit($account, $amount);

            return $this->recordTransaction($account->id, Transaction::TYPE_EXPENSE, $amount, $description ?? 'Saque');
        });
    }

    /**
     * Retorna o histórico de transações do usuário, paginado com filtros.
     */
    public function getTransactions(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Transaction::query()
            ->whereHas('account', fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('occurred_at', '>=', Carbon::parse($filters['date_from']));
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('occurred_at', '<=', Carbon::parse($filters['date_to']));
        }

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->paginate($perPage);
    }

    /**
     * Persiste o registro da transação na base de dados.
     */
    private function recordTransaction(int $accountId, string $type, float $amount, string $description): Transaction
    {
        return Transaction::create([
            'account_id' => $accountId,
            'type' => $type,
            'status' => Transaction::STATUS_REALIZED,
            'amount' => $amount,
            'description' => $description,
            'occurred_at' => Carbon::today(),
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
