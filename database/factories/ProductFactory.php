<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generamos precios lógicos: Costo < Precio Empleado < Precio Público
        $cost = $this->faker->randomFloat(2, 10, 50); 
        $price = $cost * 3; 
        $employeePrice = $cost * 1.5;

        return [
            'name' => $this->faker->unique()->words(3, true), // Ej: "Hamburguesa Doble Queso"
            'barcode' => $this->faker->unique()->ean13(),     // Código de barras simulado
            'description' => $this->faker->sentence(),
            'price' => $price,
            'employee_price' => $employeePrice,
            'cost' => $cost,
            'is_sellable' => true,
            'track_inventory' => true,
            'is_active' => true,
            // 'category_id' => Category::factory(), // Opcional: Si decides crear un CategoryFactory después
        ];
    }
}