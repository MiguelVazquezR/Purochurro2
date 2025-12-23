<?php

namespace Tests\Feature;

use App\Models\DailyOperation;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SaleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_list_sales_history()
    {
        // Crear datos de prueba
        $operation = DailyOperation::create(['date' => now(), 'cash_start' => 500]);
        
        // Crear 3 ventas
        Sale::factory()->count(3)->create([
            'daily_operation_id' => $operation->id
        ]);

        $response = $this->get(route('sales.index'));

        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sale/Index')
            ->has('sales.data', 3) // Paginación
        );
    }

    public function test_can_show_sale_details()
    {
        // 1. Setup
        $operation = DailyOperation::create(['date' => now(), 'cash_start' => 500]);
        $product = Product::factory()->create(['name' => 'Tacos', 'price' => 15]);
        
        // 2. Crear Venta
        $sale = Sale::factory()->create([
            'daily_operation_id' => $operation->id,
            'total' => 30.00
        ]);

        // 3. Crear Detalle
        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 15,
            'subtotal' => 30.00
        ]);

        // 4. Petición al endpoint show
        $response = $this->get(route('sales.show', $sale));

        $response->assertStatus(200);

        // 5. Verificar que Inertia recibe los detalles correctos
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sale/Show')
            ->where('sale.total', 30) // Verifica total
            ->has('sale.details', 1)     // Verifica que carga items
            ->where('sale.details.0.product.name', 'Tacos') // Verifica relación producto
        );
    }
}