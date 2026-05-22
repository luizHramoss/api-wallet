<?php

namespace Database\Factories;

use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        $type   = fake()->randomElement(['credit', 'debit']);
        $amount = fake()->randomFloat(2, 0.01, 1000);

        return [
            'wallet_id'    => Wallet::factory(),
            'type'         => $type,
            'amount'       => $amount,
            'balance_after' => fake()->randomFloat(2, 0, 5000),
            'description'  => $type === 'credit' ? 'Depósito' : 'Saque',
        ];
    }

    public function credit(): static
    {
        return $this->state(fn () => ['type' => 'credit', 'description' => 'Depósito']);
    }

    public function debit(): static
    {
        return $this->state(fn () => ['type' => 'debit', 'description' => 'Saque']);
    }
}
