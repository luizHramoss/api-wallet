<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\RecurringBill;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringBillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'name' => fake()->randomElement(['Aluguel', 'Internet', 'Energia', 'Assinatura', 'Academia', 'Seguro']),
            'type' => 'expense',
            'amount' => fake()->randomFloat(2, 20, 2000),
            'day_of_month' => fake()->numberBetween(1, 28),
            'start_date' => now()->subMonths(3)->startOfMonth(),
            'status' => RecurringBill::STATUS_ACTIVE,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => RecurringBill::STATUS_PAUSED]);
    }

    public function income(): static
    {
        return $this->state(fn () => ['type' => 'income']);
    }
}
