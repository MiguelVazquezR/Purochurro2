<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'birth_date' => $this->faker->dateTimeBetween('-50 years', '-18 years'),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'email' => $this->faker->unique()->safeEmail(),
            'hired_at' => $this->faker->dateTimeBetween('-5 years', 'now'),
            'base_salary' => $this->faker->randomFloat(2, 1200, 5000), // Salario semanal/quincenal simulado
            'is_active' => true,
            'default_schedule_template' => [
                'monday' => 'descanso',
                'tuesday' => 'matutino',
                'wednesday' => 'vespertino',
                'thursday' => 'matutino',
                'friday' => 'doble',
                'saturday' => 'doble',
                'sunday' => 'vespertino'
            ],
        ];
    }
}