<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear Almacén Principal 'Cocina'
        // 'is_sales_point' => false porque aquí solo se produce/almacena
        Location::firstOrCreate(
            ['name' => 'Cocina'], 
            [
                'slug' => 'cocina',
                'is_sales_point' => false 
            ]
        );

        // 2. Crear Punto de Venta 'Carrito'
        // 'is_sales_point' => true para que aparezca en el POS
        Location::firstOrCreate(
            ['name' => 'Carrito'], 
            [
                'slug' => 'carrito',
                'is_sales_point' => true
            ]
        );

        // Opcional: Si quieres un segundo punto de venta para pruebas
        // Location::firstOrCreate(
        //     ['name' => 'Carrito 2 (Plaza Norte)'], 
        //     [
        //         'slug' => 'carrito-2',
        //         'is_sales_point' => true
        //     ]
        // );
    }
}