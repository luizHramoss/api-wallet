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

        if (! empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
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
     * Cria uma transação avulsa (income/expense numa conta, ou transfer
     * entre duas contas do mesmo usuário). Só mexe no saldo quando o status
     * é realized - uma transação planned fica só "reservada" pra visibilidade
     * futura, sem impacto imediato (ver RecurringBillService).
     *
     * @throws InvalidTransactionException|Throwable
     */
    public function create(User $user, array $data): Transaction
    {
        $this->assertPositiveAmount((float) $data['amount']);

        if ($data['type'] === Transaction::TYPE_TRANSFER) {
            return $this->createTransfer($user, $data);
        }

        return DB::transaction(function () use ($user, $data) {
            $account = $this->accountService->lockAndFind($user, $data['account_id']);
            $status = $data['status'] ?? Transaction::STATUS_REALIZED;

            if ($status === Transaction::STATUS_REALIZED) {
                $data['type'] === Transaction::TYPE_INCOME
                    ? $this->accountService->credit($account, (float) $data['amount'])
                    : $this->accountService->debit($account, (float) $data['amount']);
            }

            return Transaction::create([
                'account_id' => $account->id,
                'category_id' => $data['category_id'] ?? null,
                'type' => $data['type'],
                'status' => $status,
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? Carbon::today(),
            ]);
        });
    }

    /**
     * Transferência entre duas contas do usuário - gera duas linhas
     * (out na conta de origem, in na conta de destino), uma apontando pra
     * outra via transfer_pair_id, ambas criadas/desfeitas atomicamente.
     */
    private function createTransfer(User $user, array $data): Transaction
    {
        if ((int) $data['account_id'] === (int) $data['to_account_id']) {
            throw new InvalidTransactionException('A conta de origem e destino não podem ser a mesma.');
        }

        return DB::transaction(function () use ($user, $data) {
            $from = $this->accountService->lockAndFind($user, $data['account_id']);
            $to = $this->accountService->lockAndFind($user, $data['to_account_id']);

            $status = $data['status'] ?? Transaction::STATUS_REALIZED;
            $occurredAt = $data['occurred_at'] ?? Carbon::today();
            $amount = (float) $data['amount'];

            if ($status === Transaction::STATUS_REALIZED) {
                $this->accountService->debit($from, $amount);
                $this->accountService->credit($to, $amount);
            }

            $outLeg = Transaction::create([
                'account_id' => $from->id,
                'category_id' => $data['category_id'] ?? null,
                'type' => Transaction::TYPE_TRANSFER,
                'status' => $status,
                'amount' => $amount,
                'description' => $data['description'] ?? 'Transferência enviada',
                'occurred_at' => $occurredAt,
                'transfer_direction' => Transaction::TRANSFER_OUT,
            ]);

            $inLeg = Transaction::create([
                'account_id' => $to->id,
                'category_id' => $data['category_id'] ?? null,
                'type' => Transaction::TYPE_TRANSFER,
                'status' => $status,
                'amount' => $amount,
                'description' => $data['description'] ?? 'Transferência recebida',
                'occurred_at' => $occurredAt,
                'transfer_direction' => Transaction::TRANSFER_IN,
                'transfer_pair_id' => $outLeg->id,
            ]);

            $outLeg->update(['transfer_pair_id' => $inLeg->id]);

            return $outLeg;
        });
    }

    /**
     * Atualiza valor/categoria/descrição/data/status de uma transação já
     * existente, ajustando o saldo da conta pra refletir a diferença (desfaz
     * o impacto antigo se ela era realized, aplica o novo se o status
     * resultante é realized). Transferências não podem ser editadas por
     * aqui - o par de linhas tornaria a reversão ambígua; exclua e recrie.
     *
     * @throws InvalidTransactionException|Throwable
     */
    public function update(Transaction $transaction, array $data): Transaction
    {
        if ($transaction->isTransfer()) {
            throw new InvalidTransactionException('Transferências não podem ser editadas. Exclua e crie novamente.');
        }

        if (isset($data['amount'])) {
            $this->assertPositiveAmount((float) $data['amount']);
        }

        return DB::transaction(function () use ($transaction, $data) {
            $account = $this->accountService->lockById($transaction->account_id);

            $oldStatus = $transaction->status;
            $oldAmount = (float) $transaction->amount;
            $newStatus = $data['status'] ?? $oldStatus;
            $newAmount = isset($data['amount']) ? (float) $data['amount'] : $oldAmount;

            if ($oldStatus === Transaction::STATUS_REALIZED) {
                $transaction->isIncome()
                    ? $this->accountService->debit($account, $oldAmount)
                    : $this->accountService->credit($account, $oldAmount);
            }

            if ($newStatus === Transaction::STATUS_REALIZED) {
                $transaction->isIncome()
                    ? $this->accountService->credit($account, $newAmount)
                    : $this->accountService->debit($account, $newAmount);
            }

            $transaction->fill([
                'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $transaction->category_id,
                'amount' => $newAmount,
                'description' => $data['description'] ?? $transaction->description,
                'occurred_at' => $data['occurred_at'] ?? $transaction->occurred_at,
                'status' => $newStatus,
            ]);
            $transaction->save();

            return $transaction->fresh();
        });
    }

    /**
     * Exclui uma transação, desfazendo o impacto no saldo se ela era
     * realized. Para transferências, exclui e reverte as duas pernas juntas.
     *
     * @throws Throwable
     */
    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            if ($transaction->isTransfer() && $transaction->transfer_pair_id) {
                $pair = Transaction::find($transaction->transfer_pair_id);
                $this->reverseAndDelete($transaction);
                if ($pair) {
                    $this->reverseAndDelete($pair);
                }

                return;
            }

            $this->reverseAndDelete($transaction);
        });
    }

    private function reverseAndDelete(Transaction $transaction): void
    {
        if ($transaction->isRealized()) {
            $account = $this->accountService->lockById($transaction->account_id);

            if ($transaction->isTransfer()) {
                $transaction->transfer_direction === Transaction::TRANSFER_OUT
                    ? $this->accountService->credit($account, (float) $transaction->amount)
                    : $this->accountService->debit($account, (float) $transaction->amount);
            } else {
                $transaction->isIncome()
                    ? $this->accountService->debit($account, (float) $transaction->amount)
                    : $this->accountService->credit($account, (float) $transaction->amount);
            }
        }

        $transaction->delete();
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
