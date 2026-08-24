<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\User;
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
     * Retorna (sem travar) a conta padrão do usuário - a primeira não arquivada.
     * Usada em leituras (GET) enquanto o usuário só tem uma conta e não precisa
     * escolher explicitamente.
     *
     * @throws ModelNotFoundException
     */
    public function firstAccountFor(User $user): Account
    {
        return Account::where('user_id', $user->id)
            ->where('is_archived', false)
            ->oldest('id')
            ->firstOrFail();
    }

    /**
     * Mesma resolução que firstAccountFor(), mas travando a linha (lockForUpdate)
     * para uso dentro de uma DB::transaction() que vai mutar o saldo.
     *
     * @throws ModelNotFoundException
     */
    public function lockDefaultAccountFor(User $user): Account
    {
        return Account::lockForUpdate()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->oldest('id')
            ->firstOrFail();
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
