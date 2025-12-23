<?php

namespace Tests\Feature;

use App\Models\DailyOperation;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_locations_and_products()
    {
        // 1. Crear Ubicaciones
        $cocina = Location::create(['name' => 'Cocina', 'slug' => 'cocina', 'is_sales_point' => false]);
        $carrito = Location::create(['name' => 'Carrito', 'slug' => 'carrito', 'is_sales_point' => true]);

        // 2. Crear Producto (Ej: Coca Cola)
        $coca = Product::create([
            'name' => 'Coca Cola 600ml',
            'price' => 25.00,        // Público
            'employee_price' => 20.00, // Empleado
            'is_sellable' => true
        ]);

        // 3. Inicializar Inventario Diferenciado
        Inventory::create(['location_id' => $cocina->id, 'product_id' => $coca->id, 'quantity' => 100]); // 100 en almacén
        Inventory::create(['location_id' => $carrito->id, 'product_id' => $coca->id, 'quantity' => 10]); // 10 en venta

        // Aserciones
        $this->assertEquals(100, $coca->fresh()->stockIn($cocina));
        $this->assertEquals(10, $coca->fresh()->stockIn($carrito));
        
        // Verificar flags
        $this->assertTrue($carrito->is_sales_point);
        $this->assertFalse($cocina->is_sales_point);
    }

    public function test_can_track_non_sellable_items()
    {
        // Crear Azúcar (Insumo)
        $sugar = Product::create([
            'name' => 'Azúcar 1kg',
            'is_sellable' => false, // No debe salir en el POS para venta
            'track_inventory' => true
        ]);

        $this->assertFalse($sugar->is_sellable);
    }

    public function test_can_assign_employees_to_locations_daily()
    {
        // Preparar
        $chef = Employee::factory()->create(['first_name' => 'Chef']);
        $cajero = Employee::factory()->create(['first_name' => 'Cajero']);
        
        $cocina = Location::create(['name' => 'Cocina', 'slug' => 'cocina']);
        $carrito = Location::create(['name' => 'Carrito', 'slug' => 'carrito']);

        // Crear la operación del día
        $todayOp = DailyOperation::create([
            'date' => now(),
            'cash_start' => 500.00
        ]);

        // Asignar personal
        $todayOp->staff()->attach($chef->id, ['location_id' => $cocina->id]);
        $todayOp->staff()->attach($cajero->id, ['location_id' => $carrito->id]);

        // Verificar
        $this->assertDatabaseHas('daily_operation_employee', [
            'employee_id' => $chef->id,
            'location_id' => $cocina->id
        ]);
        
        $this->assertDatabaseHas('daily_operation_employee', [
            'employee_id' => $cajero->id,
            'location_id' => $carrito->id
        ]);
    }
}