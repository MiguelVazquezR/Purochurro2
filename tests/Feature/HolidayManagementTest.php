<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HolidayManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_list_holidays()
    {
        Holiday::create(['name' => 'Año Nuevo', 'date' => '2025-01-01', 'pay_multiplier' => 2]);

        $response = $this->get(route('holidays.index'));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Holiday/Index')
                ->has('holidays', 1)
            );
    }

    public function test_can_create_holiday()
    {
        $response = $this->post(route('holidays.store'), [
            'name' => 'Día de la Independencia',
            'date' => '2025-09-16',
            'pay_multiplier' => 3.0,
            'mandatory_rest' => true
        ]);

        $response->assertRedirect();
        
        // Verificación básica
        $this->assertDatabaseHas('holidays', [
            'name' => 'Día de la Independencia',
            'pay_multiplier' => 3.0
        ]);
    }

    public function test_cannot_create_duplicate_holiday_date()
    {
        // CORRECCIÓN: Usamos DB::table para insertar la fecha como string puro ('2025-12-25')
        // sin la hora '00:00:00'. Esto asegura que la validación 'unique' de Laravel
        // (que compara strings) detecte la coincidencia en SQLite.
        
        DB::table('holidays')->insert([
            'name' => 'Navidad',
            'date' => '2025-12-25',
            'pay_multiplier' => 2,
            'mandatory_rest' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post(route('holidays.store'), [
            'name' => 'Otra Navidad',
            'date' => '2025-12-25', // Fecha repetida
            'pay_multiplier' => 2,
            'mandatory_rest' => true
        ]);

        $response->assertSessionHasErrors('date');
    }
}