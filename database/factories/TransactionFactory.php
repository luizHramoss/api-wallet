<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement([Transaction::TYPE_INCOME, Transaction::TYPE_EXPENSE]);

        return [
            'account_id' => Account::factory(),
            'type' => $type,
            'status' => Transaction::STATUS_REALIZED,
            'amount' => fake()->randomFloat(2, 0.01, 1000),
            'description' => $type === Transaction::TYPE_INCOME ? 'Depósito' : 'Saque',
            'occurred_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function credit(): static
    {
        return $this->state(fn () => ['type' => Transaction::TYPE_INCOME, 'description' => 'Depósito']);
    }

    public function debit(): static
    {
        return $this->state(fn () => ['type' => Transaction::TYPE_EXPENSE, 'description' => 'Saque']);
    }

    public function planned(): static
    {
        return $this->state(fn () => ['status' => Transaction::STATUS_PLANNED]);
    }
}
