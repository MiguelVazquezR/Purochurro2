<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        // Autenticamos un usuario para todas las pruebas
        $this->actingAs(User::factory()->create());
    }

    public function test_can_list_products()
    {
        // Crear 3 productos de prueba
        Product::factory()->count(3)->create();

        $response = $this->get(route('products.index'));

        $response->assertStatus(200);

        // Verificamos que se renderice el componente de Inertia correcto
        // y que pasemos la lista de productos
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Product/Index')
            ->has('products', 3) 
        );
    }

    public function test_can_create_product_with_valid_data()
    {
        $productData = [
            'name' => 'Hamburguesa Doble',
            'barcode' => 'HAM-001',
            'price' => 150.00,
            'employee_price' => 120.00,
            'cost' => 80.00,
            'is_sellable' => true,
            'track_inventory' => true,
            'description' => 'Deliciosa hamburguesa con doble carne',
        ];

        $response = $this->post(route('products.store'), $productData);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'name' => 'Hamburguesa Doble',
            'barcode' => 'HAM-001',
            'price' => 150.00,
        ]);
    }

    public function test_can_create_product_with_image()
    {
        // Fake del disco público para no guardar archivos reales
        Storage::fake('public');

        $file = UploadedFile::fake()->image('hamburguesa.jpg');

        $productData = [
            'name' => 'Hamburguesa con Queso',
            'price' => 100.00,
            'is_sellable' => true,
            'image' => $file, // Enviamos el archivo
        ];

        $this->post(route('products.store'), $productData);

        // Obtenemos el producto creado
        $product = Product::where('name', 'Hamburguesa con Queso')->first();

        // Aserciones de Spatie Media Library
        $this->assertNotNull($product);
        $this->assertTrue($product->hasMedia('product_image'));
        
        // Verificar que el archivo existe en el disco fake
        // Nota: Spatie guarda en directorios por ID, verificamos que tenga media
        $this->assertCount(1, $product->getMedia('product_image'));
    }

    public function test_cannot_create_product_with_invalid_data()
    {
        // Intentar crear sin nombre y sin precio
        $response = $this->post(route('products.store'), [
            'is_sellable' => true,
        ]);

        $response->assertSessionHasErrors(['name', 'price']);
    }

    public function test_cannot_create_product_with_duplicate_barcode()
    {
        // Crear un producto existente
        Product::create([
            'name' => 'Producto A',
            'barcode' => 'CODE-123',
            'price' => 10.00
        ]);

        // Intentar crear otro con el mismo barcode
        $response = $this->post(route('products.store'), [
            'name' => 'Producto B',
            'barcode' => 'CODE-123', // Duplicado
            'price' => 20.00,
        ]);

        $response->assertSessionHasErrors(['barcode']);
    }

    public function test_can_update_product()
    {
        $product = Product::create([
            'name' => 'Papas Fritas',
            'price' => 50.00,
            'is_sellable' => true
        ]);

        $updateData = [
            'name' => 'Papas Fritas Grandes', // Cambio de nombre
            'price' => 60.00,                 // Cambio de precio
            'is_sellable' => true,
        ];

        $response = $this->put(route('products.update', $product), $updateData);

        $response->assertRedirect(route('products.index'));
        
        // Verificar cambios en DB
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Papas Fritas Grandes',
            'price' => 60.00
        ]);
    }

    public function test_can_update_product_image()
    {
        Storage::fake('public');
        
        $product = Product::create(['name' => 'Pizza', 'price' => 200]);
        
        // 1. Subir primera imagen
        $file1 = UploadedFile::fake()->image('pizza_old.jpg');
        $product->addMedia($file1)->toMediaCollection('product_image');
        
        // Verificar que tiene imagen
        $this->assertCount(1, $product->refresh()->getMedia('product_image'));

        // 2. Actualizar con nueva imagen
        $file2 = UploadedFile::fake()->image('pizza_new.jpg');
        
        $response = $this->put(route('products.update', $product), [
            'name' => 'Pizza',
            'price' => 200,
            'image' => $file2
        ]);

        // 3. Verificar que Spatie reemplazó la imagen (singleFile en el modelo)
        $product->refresh();
        $this->assertCount(1, $product->getMedia('product_image'));
        $this->assertEquals('pizza_new.jpg', $product->getFirstMedia('product_image')->file_name);
    }

    public function test_can_soft_delete_product()
    {
        $product = Product::create([
            'name' => 'Refresco',
            'price' => 25.00
        ]);

        $response = $this->delete(route('products.destroy', $product));

        $response->assertRedirect(route('products.index'));

        // Verificar Soft Delete
        $this->assertSoftDeleted('products', [
            'id' => $product->id
        ]);
    }
}