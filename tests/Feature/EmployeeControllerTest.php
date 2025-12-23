<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 1. Crear ADMIN primero para asegurar que obtenga el ID 1
        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
        ]);

        // 2. Crear Usuario Normal (obtendrá ID 2)
        $this->user = User::factory()->create([
            'name' => 'Normal User',
            'email' => 'normal@example.com',
            'email_verified_at' => now(),
        ]);
        
        // Por defecto actuamos como usuario normal (ID 2)
        $this->actingAs($this->user);
    }

    public function test_can_list_employees_via_inertia()
    {
        Employee::factory()->count(3)->create();
        $response = $this->get(route('employees.index'));
        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Employee/Index')->has('employees.data', 3));
    }

    public function test_can_view_create_page()
    {
        $response = $this->get(route('employees.create'));
        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Employee/Create'));
    }

    public function test_can_view_edit_page()
    {
        $employee = Employee::factory()->create();
        $response = $this->get(route('employees.edit', $employee));
        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Employee/Edit'));
    }

    public function test_can_view_show_page()
    {
        $employee = Employee::factory()->create();
        $response = $this->get(route('employees.show', $employee));
        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Employee/Show'));
    }

    public function test_can_search_employees()
    {
        $this->withoutExceptionHandling();
        Employee::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        Employee::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);

        $response = $this->get(route('employees.index', ['search' => 'John']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.first_name', 'John')
        );
    }

    public function test_can_create_employee_with_photo()
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('face.jpg');

        $data = [
            'first_name' => 'Carlos',
            'last_name' => 'Perez',
            'birth_date' => '1990-01-01',
            'phone' => '555-1234',
            'address' => 'Calle Falsa 123',
            'email' => 'carlos@test.com',
            'hired_at' => '2023-01-01',
            'base_salary' => 1500.50,
            'photo' => $file,
            'default_schedule_template' => []
        ];

        $response = $this->post(route('employees.store'), $data);

        $response->assertRedirect(route('employees.index'));
        
        $this->assertDatabaseHas('employees', ['email' => 'carlos@test.com']);
        $employee = Employee::where('email', 'carlos@test.com')->first();
        
        $this->assertNotNull($employee->user_id);
        $this->assertDatabaseHas('users', ['email' => 'carlos@test.com']);
        $this->assertTrue($employee->hasMedia('avatar'));
    }

    public function test_validates_employee_input()
    {
        $response = $this->post(route('employees.store'), [
            'first_name' => '',
            'email' => 'not-an-email',
        ]);
        $response->assertSessionHasErrors(['first_name', 'email', 'last_name', 'base_salary']);
    }

    public function test_can_update_employee()
    {
        $employee = Employee::factory()->create(['base_salary' => 1000, 'email' => 'old@test.com']);

        $response = $this->put(route('employees.update', $employee), [
            'first_name' => 'Updated Name',
            'last_name' => $employee->last_name,
            'birth_date' => $employee->birth_date->format('Y-m-d'),
            'phone' => $employee->phone,
            'address' => $employee->address,
            'base_salary' => 2000,
            'hired_at' => $employee->hired_at->format('Y-m-d'),
            'email' => 'updated@test.com', 
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'first_name' => 'Updated Name',
            'email' => 'updated@test.com',
        ]);
    }

    public function test_can_delete_employee()
    {
        $employee = Employee::factory()->create();
        $response = $this->delete(route('employees.destroy', $employee));
        $response->assertRedirect();
        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
    }

    // --- CORRECCIONES APLICADAS ---

    public function test_can_terminate_employee_with_justification()
    {
        $this->actingAs($this->admin);

        $employee = Employee::factory()->create([
            'is_active' => true,
            'hired_at' => now()->subYears(2)
        ]);

        $response = $this->post(route('employees.terminate', $employee), [
            'termination_date' => now()->format('Y-m-d'),
            'reason' => 'unjustified',
            'notes' => 'Recorte de personal',
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'is_active' => false,
            'termination_reason' => 'unjustified',
        ]);
    }

    public function test_admin_can_see_severance_calculation()
    {
        $this->actingAs($this->admin);

        $employee = Employee::factory()->create([
            'hired_at' => now()->subYear(), 
            'base_salary' => 333.33, 
        ]);

        $response = $this->get(route('employees.show', $employee));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Employee/Show')
            ->has('severance_data')
            // Corrección: Esperamos string "1.00" en lugar de int 1
            ->where('severance_data.years_worked', '1.00')
        );
    }

    public function test_non_admin_cannot_see_severance_calculation()
    {
        $this->actingAs($this->user);

        $employee = Employee::factory()->create();

        $response = $this->get(route('employees.show', $employee));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Employee/Show')
            // Corrección: Verificamos que sea NULL en lugar de "missing", 
            // ya que la llave siempre se envía desde el controlador.
            ->where('severance_data', null) 
        );
    }

    public function test_calculates_vacations_correctly_mexico_law()
    {
        $this->actingAs($this->admin);

        $employee = Employee::factory()->create([
            'hired_at' => now()->subYear(), 
        ]);

        $response = $this->get(route('employees.show', $employee));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('vacation_stats.total_days', 12)
        );
    }
}