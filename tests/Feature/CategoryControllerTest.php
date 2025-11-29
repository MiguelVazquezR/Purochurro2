<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_list_categories()
    {
        Category::factory()->count(5)->create();

        $response = $this->get(route('categories.index'));

        $response->assertStatus(200);
        
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Category/Index')
            ->has('categories', 5)
        );
    }

    public function test_can_create_category()
    {
        $data = [
            'name' => 'Bebidas Calientes',
            'color' => '#FF5733', // Naranja
        ];

        $response = $this->post(route('categories.store'), $data);

        $response->assertRedirect(route('categories.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('categories', [
            'name' => 'Bebidas Calientes',
            'color' => '#FF5733',
        ]);
    }

    public function test_cannot_create_category_with_invalid_color()
    {
        // El color debe ser hexadecimal (ej: #FFFFFF)
        $response = $this->post(route('categories.store'), [
            'name' => 'Mal Color',
            'color' => 'rojo-fuerte', // Inválido
        ]);

        $response->assertSessionHasErrors(['color']);
    }

    public function test_can_update_category()
    {
        $category = Category::factory()->create();

        $response = $this->put(route('categories.update', $category), [
            'name' => 'Nombre Editado',
            'color' => '#000000'
        ]);

        $response->assertRedirect(route('categories.index'));
        
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Nombre Editado',
            'color' => '#000000'
        ]);
    }

    public function test_can_delete_category()
    {
        $category = Category::factory()->create();

        $response = $this->delete(route('categories.destroy', $category));

        $response->assertRedirect(route('categories.index'));
        
        $this->assertDatabaseMissing('categories', [
            'id' => $category->id
        ]);
    }
}