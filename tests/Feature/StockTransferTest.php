<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_transfer_stock_between_locations()
    {
        // 1. Setup: Cocina (100) -> Carrito (0)
        $cocina = Location::create(['name' => 'Cocina', 'slug' => 'cocina']);
        $carrito = Location::create(['name' => 'Carrito', 'slug' => 'carrito']);
        $coca = Product::create(['name' => 'Coca', 'is_active' => true]);

        Inventory::create(['location_id' => $cocina->id, 'product_id' => $coca->id, 'quantity' => 100]);

        // 2. Ejecutar Traspaso de 20 unidades
        $response = $this->post(route('stock-transfers.store'), [
            'product_id' => $coca->id,
            'from_location_id' => $cocina->id,
            'to_location_id' => $carrito->id,
            'quantity' => 20,
            'notes' => 'Surtido matutino'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 3. Verificar Saldos Finales
        $this->assertEquals(80, Inventory::where('location_id', $cocina->id)->where('product_id', $coca->id)->value('quantity'));
        $this->assertEquals(20, Inventory::where('location_id', $carrito->id)->where('product_id', $coca->id)->value('quantity'));

        // 4. Verificar Historial
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $coca->id,
            'from_location_id' => $cocina->id,
            'to_location_id' => $carrito->id,
            'quantity' => 20,
            'notes' => 'Surtido matutino'
        ]);
    }

    public function test_cannot_transfer_if_insufficient_stock()
    {
        $cocina = Location::create(['name' => 'Cocina', 'slug' => 'cocina']);
        $carrito = Location::create(['name' => 'Carrito', 'slug' => 'carrito']);
        $coca = Product::create(['name' => 'Coca', 'is_active' => true]);

        // Solo hay 5 en cocina
        Inventory::create(['location_id' => $cocina->id, 'product_id' => $coca->id, 'quantity' => 5]);

        // Intentamos mover 10
        $response = $this->post(route('stock-transfers.store'), [
            'product_id' => $coca->id,
            'from_location_id' => $cocina->id,
            'to_location_id' => $carrito->id,
            'quantity' => 10, 
        ]);

        $response->assertSessionHas('error'); // Debe dar error
        
        // Verificar que no se movió nada
        $this->assertEquals(5, Inventory::where('location_id', $cocina->id)->value('quantity'));
        $this->assertEquals(0, Inventory::where('location_id', $carrito->id)->count()); // No se creó inventario destino
    }
}