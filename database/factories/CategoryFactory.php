<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Alimentação', 'Transporte', 'Lazer', 'Salário', 'Moradia']),
            'type' => fake()->randomElement(Category::TYPES),
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => ['type' => Category::TYPE_INCOME]);
    }

    public function expense(): static
    {
        return $this->state(fn () => ['type' => Category::TYPE_EXPENSE]);
    }
}
