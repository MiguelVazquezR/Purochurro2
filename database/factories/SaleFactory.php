<?php

namespace Database\Factories;

use App\Models\DailyOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'daily_operation_id' => DailyOperation::factory(), // O crea una si no existe
            'user_id' => User::factory(),
            'total' => $this->faker->randomFloat(2, 10, 500),
            'payment_method' => $this->faker->randomElement(['cash', 'card']),
        ];
    }
}