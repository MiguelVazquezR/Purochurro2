<?php

namespace Tests\Feature;

use App\Enums\IncidentType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VacationLogicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_deducts_vacation_day_when_incident_is_set()
    {
        // Empleado con 5 días de vacaciones
        $employee = Employee::factory()->create(['vacation_balance' => 5.0]);
        $date = Carbon::tomorrow();

        // Admin marca Vacaciones
        $this->post(route('payroll.update-day'), [
            'employee_id' => $employee->id,
            'date' => $date->format('Y-m-d'),
            'incident_type' => IncidentType::VACACIONES->value,
            'admin_notes' => 'Se va a la playa',
        ]);

        $employee->refresh();
        
        // Debe tener 4 días
        $this->assertEquals(4.0, $employee->vacation_balance);
        
        // Verificar Log
        $this->assertDatabaseHas('vacation_logs', [
            'employee_id' => $employee->id,
            'type' => 'usage',
            'days' => -1,
            'balance_before' => 5.0,
            'balance_after' => 4.0,
        ]);
    }

    public function test_refunds_vacation_day_when_incident_is_removed()
    {
        $employee = Employee::factory()->create(['vacation_balance' => 4.0]);
        $date = Carbon::today();

        // Estado inicial: Ya tiene vacaciones registradas hoy
        Attendance::create([
            'employee_id' => $employee->id,
            'date' => $date->format('Y-m-d'),
            'incident_type' => IncidentType::VACACIONES,
        ]);

        // Admin corrige: En realidad vino a trabajar (ASISTENCIA)
        $this->post(route('payroll.update-day'), [
            'employee_id' => $employee->id,
            'date' => $date->format('Y-m-d'),
            'incident_type' => IncidentType::ASISTENCIA->value,
        ]);

        $employee->refresh();

        // Debe haber recuperado su día (4 + 1 = 5)
        $this->assertEquals(5.0, $employee->vacation_balance);
        
        // Verificar Log de ajuste
        $this->assertDatabaseHas('vacation_logs', [
            'type' => 'adjustment',
            'days' => 1,
        ]);
    }

    public function test_accrues_weekly_vacation_on_payroll_closing()
    {
        $employee = Employee::factory()->create(['vacation_balance' => 0.0]);
        $start = Carbon::parse('2025-03-02'); // Domingo

        // Ejecutar Cierre de Nómina
        $this->post(route('payroll.store-settlement'), [
            'start_date' => $start->format('Y-m-d'),
        ]);

        $employee->refresh();

        // Debe haber ganado 6/52 = 0.1153...
        $expected = 6 / 52;
        
        // Usamos delta para comparar flotantes
        $this->assertEqualsWithDelta($expected, $employee->vacation_balance, 0.0001);

        $this->assertDatabaseHas('vacation_logs', [
            'employee_id' => $employee->id,
            'type' => 'accrual',
        ]);
    }
}