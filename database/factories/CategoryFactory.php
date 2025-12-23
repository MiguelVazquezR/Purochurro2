<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(), // Ej: "Bebidas", "Postres"
            'color' => $this->faker->hexColor(),      // Ej: "#a3c9a8"
        ];
    }
}