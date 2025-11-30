<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'concept' => $this->faker->sentence(3), // Ej: "Jabón para trastes"
            'amount' => $this->faker->randomFloat(2, 50, 1000), // Monto entre 50 y 1000
            'date' => $this->faker->date(),
            'notes' => $this->faker->optional()->sentence(),
            'user_id' => User::factory(), // Crea un usuario si no se pasa uno
        ];
    }
}