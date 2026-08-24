<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AccountService
{
    /**
     * Cria a conta padrão (checking) para o usuário recém-registrado, com saldo zero.
     */
    public function createDefaultForUser(User $user): Account
    {
        return $this->create($user, [
            'name' => 'Conta Principal',
            'type' => Account::TYPE_CHECKING,
            'balance' => 0.00,
        ]);
    }

    public function create(User $user, array $data): Account
    {
        return $user->accounts()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'balance' => $data['balance'] ?? 0.00,
            'color' => $data['color'] ?? null,
        ]);
    }

    /**
     * Lista todas as contas do usuário (inclusive arquivadas - quem decide
     * o que exibir é o consumidor), ordenadas pelas mais antigas primeiro.
     */
    public function listFor(User $user): Collection
    {
        return $user->accounts()->oldest('id')->get();
    }

    public function update(Account $account, array $data): Account
    {
        $account->fill(array_intersect_key($data, array_flip(['name', 'type', 'color'])));
        $account->save();

        return $account;
    }

    public function archive(Account $account): Account
    {
        $account->is_archived = true;
        $account->save();

        return $account;
    }

    public function unarchive(Account $account): Account
    {
        $account->is_archived = false;
        $account->save();

        return $account;
    }

    /**
     * Busca e trava (lockForUpdate) uma conta do usuário para mutação segura de saldo.
     *
     * @throws ModelNotFoundException
     */
    public function lockAndFind(User $user, int $accountId): Account
    {
        return Account::lockForUpdate()
            ->where('user_id', $user->id)
            ->findOrFail($accountId);
    }

    /**
     * Trava (lockForUpdate) uma conta por id sem escopar por usuário - usado
     * quando a posse já foi verificada antes (ex: TransactionService, a
     * partir de um Transaction já resolvido via $user->transactions()).
     *
     * @throws ModelNotFoundException
     */
    public function lockById(int $accountId): Account
    {
        return Account::lockForUpdate()->findOrFail($accountId);
    }

    public function credit(Account $account, float $amount): void
    {
        $account->balance = round((float) $account->balance + $amount, 2);
        $account->save();
    }

    /**
     * @throws InsufficientBalanceException
     */
    public function debit(Account $account, float $amount): void
    {
        if (! $account->hasSufficientBalance($amount)) {
            throw new InsufficientBalanceException(
                'Saldo insuficiente. Saldo disponível: R$ '.number_format($account->balance, 2, ',', '.')
            );
        }

        $account->balance = round((float) $account->balance - $amount, 2);
        $account->save();
    }
}
