<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'balance' => fake()->randomFloat(2, 0, 5000),
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
}
