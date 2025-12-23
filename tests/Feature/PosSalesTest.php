<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\DailyOperation;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_open_daily_operation()
    {
        $response = $this->post(route('pos.open'), [
            'cash_start' => 1000.00,
            'notes' => 'Inicio de turno matutino'
        ]);

        $response->assertRedirect();
        
        // 1. Verificamos los datos numéricos y booleanos en la BD (son seguros)
        $this->assertDatabaseHas('daily_operations', [
            'cash_start' => 1000.00,
            'is_closed' => false,
            // 'date' => ... OMITIMOS la fecha aquí para evitar conflicto SQLite vs MySQL
        ]);

        // 2. Verificamos la fecha recuperando el modelo
        // Esto usa el 'cast' del modelo, normalizando la fecha sin importar cómo se guardó en BD
        $operation = DailyOperation::where('cash_start', 1000.00)->first();
        $this->assertNotNull($operation);
        $this->assertEquals(now()->format('Y-m-d'), $operation->date->format('Y-m-d'));
    }

    public function test_can_register_sale_and_deduct_stock()
    {
        // 1. Setup
        $carrito = Location::create(['name' => 'Carrito', 'slug' => 'carrito', 'is_sales_point' => true]);
        $coca = Product::factory()->create(['name' => 'Coca', 'price' => 25.00, 'track_inventory' => true]);
        
        Inventory::create(['location_id' => $carrito->id, 'product_id' => $coca->id, 'quantity' => 10]);

        // Crear operación manualmente
        $operation = DailyOperation::create([
            'date' => now()->format('Y-m-d'), 
            'cash_start' => 500
        ]);

        // 2. Ejecutar Venta
        $response = $this->post(route('pos.store-sale'), [
            'location_id' => $carrito->id,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $coca->id,
                    'quantity' => 2,
                    'price' => 25.00
                ]
            ]
        ]);

        $response->assertSessionHas('success');

        // 3. Verificaciones
        $this->assertDatabaseHas('sales', [
            'daily_operation_id' => $operation->id,
            'total' => 50.00,
            'payment_method' => 'cash'
        ]);

        $this->assertDatabaseHas('sale_details', [
            'product_id' => $coca->id,
            'quantity' => 2,
            'subtotal' => 50.00
        ]);

        // Inventario (10 - 2 = 8)
        $this->assertEquals(8, Inventory::where('location_id', $carrito->id)->value('quantity'));

        // Historial de Movimientos
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $coca->id,
            'type' => StockMovementType::SALE->value,
            'quantity' => 2
        ]);
    }

    public function test_cannot_sell_without_stock()
    {
        $carrito = Location::create(['name' => 'Carrito', 'slug' => 'carrito']);
        $coca = Product::factory()->create(['track_inventory' => true]);
        Inventory::create(['location_id' => $carrito->id, 'product_id' => $coca->id, 'quantity' => 1]);
        
        DailyOperation::create(['date' => now()->format('Y-m-d'), 'cash_start' => 500]);

        // Intentar vender más de lo que hay
        $response = $this->post(route('pos.store-sale'), [
            'location_id' => $carrito->id,
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $coca->id, 'quantity' => 5, 'price' => 10]
            ]
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(1, Inventory::where('location_id', $carrito->id)->value('quantity'));
    }

    public function test_can_close_daily_operation()
    {
        $operation = DailyOperation::create([
            'date' => now()->format('Y-m-d'), 
            'cash_start' => 500,
            'is_closed' => false
        ]);

        $response = $this->post(route('pos.close'), [
            'cash_end' => 1200.00,
            'notes' => 'Cierre exitoso'
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('daily_operations', [
            'id' => $operation->id,
            'cash_end' => 1200.00,
            'is_closed' => true,
            'notes' => 'Cierre exitoso'
        ]);
    }
}