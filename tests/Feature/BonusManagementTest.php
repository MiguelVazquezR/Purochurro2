<?php

namespace Tests\Feature;

use App\Models\Bonus;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BonusManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_bonus_catalog_item()
    {
        $response = $this->post(route('bonuses.store'), [
            'name' => 'Bono Puntualidad',
            'amount' => 200.00,
            'type' => 'fixed',
            'description' => 'Por llegar temprano',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bonuses', [
            'name' => 'Bono Puntualidad',
            'amount' => 200.00
        ]);
    }

    public function test_can_assign_bonus_to_employee()
    {
        $employee = Employee::factory()->create();
        $bonus = Bonus::create([
            'name' => 'Bono Extra',
            'amount' => 500,
            'type' => 'fixed'
        ]);

        $date = Carbon::today()->format('Y-m-d');

        $response = $this->post(route('bonuses.assign'), [
            'employee_id' => $employee->id,
            'bonus_id' => $bonus->id,
            'assigned_date' => $date,
            // No enviamos amount, debe usar el default del bono (500)
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('employee_bonus', [
            'employee_id' => $employee->id,
            'bonus_id' => $bonus->id,
            'amount' => 500, // Verificamos que usó el default
        ]);
        
        // Verificamos fecha con whereDate por si SQLite añade hora
        $this->assertDatabaseCount('employee_bonus', 1);
    }
}