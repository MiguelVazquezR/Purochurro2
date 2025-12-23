<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'color' => $this->faker->hexColor,
            'is_active' => true,
        ];
    }
}