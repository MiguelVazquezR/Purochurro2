<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_register_purchase_entry()
    {
        $cocina = Location::create(['name' => 'Cocina', 'slug' => 'cocina']);
        $sugar = Product::create(['name' => 'Azúcar', 'is_active' => true]);

        // Registrar Compra de 50 kgs
        $response = $this->post(route('stock-adjustments.store'), [
            'location_id' => $cocina->id,
            'product_id' => $sugar->id,
            'type' => StockMovementType::PURCHASE->value,
            'quantity' => 50,
            'notes' => 'Compra en Costco'
        ]);

        $response->assertRedirect();
        
        // Verificar Inventario
        $this->assertEquals(50, Inventory::where('location_id', $cocina->id)->value('quantity'));

        // Verificar Movimiento
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $sugar->id,
            'from_location_id' => null, // Entrada externa
            'to_location_id' => $cocina->id,
            'type' => StockMovementType::PURCHASE->value,
            'quantity' => 50
        ]);
    }

    public function test_can_register_waste_exit()
    {
        $carrito = Location::create(['name' => 'Carrito', 'slug' => 'carrito']);
        $coca = Product::create(['name' => 'Coca', 'is_active' => true]);
        
        // Inventario inicial: 10
        Inventory::create(['location_id' => $carrito->id, 'product_id' => $coca->id, 'quantity' => 10]);

        // Registrar Merma (Se rompió 1)
        $response = $this->post(route('stock-adjustments.store'), [
            'location_id' => $carrito->id,
            'product_id' => $coca->id,
            'type' => StockMovementType::WASTE->value,
            'quantity' => 1,
            'notes' => 'Botella rota'
        ]);

        $response->assertRedirect();
        
        // Verificar Inventario (10 - 1 = 9)
        $this->assertEquals(9, Inventory::where('location_id', $carrito->id)->value('quantity'));

        // Verificar Movimiento
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $coca->id,
            'from_location_id' => $carrito->id,
            'to_location_id' => null, // Salida externa
            'type' => StockMovementType::WASTE->value
        ]);
    }

    public function test_cannot_waste_more_than_stock()
    {
        $cocina = Location::create(['name' => 'Cocina', 'slug' => 'cocina']);
        $coca = Product::create(['name' => 'Coca', 'is_active' => true]);
        Inventory::create(['location_id' => $cocina->id, 'product_id' => $coca->id, 'quantity' => 5]);

        // Intentar tirar 10
        $response = $this->post(route('stock-adjustments.store'), [
            'location_id' => $cocina->id,
            'product_id' => $coca->id,
            'type' => StockMovementType::WASTE->value,
            'quantity' => 10,
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(5, Inventory::where('location_id', $cocina->id)->value('quantity'));
    }
}