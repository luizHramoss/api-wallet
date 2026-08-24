<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Conta Principal', 'Carteira', 'Poupança']),
            'type' => fake()->randomElement(Account::TYPES),
            'balance' => fake()->randomFloat(2, 0, 5000),
            'is_archived' => false,
        ];
    }

    public function empty(): static
    {
        return $this->state(fn () => ['balance' => 0.00]);
    }

    public function withBalance(float $balance): static
    {
        return $this->state(fn () => ['balance' => $balance]);
    }

    public function checking(): static
    {
        return $this->state(fn () => ['type' => Account::TYPE_CHECKING]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['is_archived' => true]);
    }
}
