<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvestmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'name' => fake()->randomElement(['Tesouro Selic', 'Petrobras PN', 'Bitcoin', 'ETF S&P 500']),
            'symbol' => fake()->optional()->currencyCode(),
            'type' => fake()->randomElement(Investment::TYPES),
            'quantity' => 0,
            'average_price' => 0,
        ];
    }

    public function withPosition(float $quantity, float $averagePrice): static
    {
        return $this->state(fn () => ['quantity' => $quantity, 'average_price' => $averagePrice]);
    }
}
