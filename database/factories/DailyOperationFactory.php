<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DailyOperationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => $this->faker->unique()->date(),
            'cash_start' => 500,
            'is_closed' => false,
        ];
    }
}