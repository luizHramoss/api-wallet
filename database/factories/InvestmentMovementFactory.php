<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\InvestmentMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvestmentMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'investment_id' => Investment::factory(),
            'type' => InvestmentMovement::TYPE_BUY,
            'quantity' => fake()->randomFloat(4, 1, 100),
            'price' => fake()->randomFloat(4, 1, 500),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'occurred_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }

    public function buy(): static
    {
        return $this->state(fn () => ['type' => InvestmentMovement::TYPE_BUY]);
    }

    public function sell(): static
    {
        return $this->state(fn () => ['type' => InvestmentMovement::TYPE_SELL]);
    }

    public function dividend(): static
    {
        return $this->state(fn () => [
            'type' => InvestmentMovement::TYPE_DIVIDEND,
            'quantity' => null,
            'price' => null,
        ]);
    }
}
