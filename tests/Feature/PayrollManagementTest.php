<?php

namespace Tests\Feature;

use App\Enums\IncidentType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class PayrollManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_view_payroll_index_periods()
    {
        $response = $this->get(route('payroll.index'));
        
        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payroll/Index')
                ->has('weeks')
            );
    }

    public function test_can_view_specific_week_details()
    {
        $employee = Employee::factory()->create();
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::SUNDAY)->format('Y-m-d');

        $response = $this->get(route('payroll.week', ['startDate' => $startOfWeek]));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payroll/Show')
                ->has('payrollData', 1)
                ->has('incidentTypes')
            );
    }

    public function test_admin_can_register_an_incident()
    {
        $employee = Employee::factory()->create();
        $date = Carbon::yesterday();

        $response = $this->post(route('payroll.update-day'), [
            'employee_id' => $employee->id,
            'date' => $date->format('Y-m-d'),
            'incident_type' => IncidentType::FALTA_INJUSTIFICADA->value,
            'admin_notes' => 'No avisó',
        ]);

        $response->assertRedirect();

        // 1. Verificamos que los campos NO fecha estén correctos en DB
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'incident_type' => IncidentType::FALTA_INJUSTIFICADA->value,
            'admin_notes' => 'No avisó',
        ]);

        // 2. Verificamos la fecha usando el modelo para evitar problemas de formato string vs datetime
        $attendance = Attendance::where('employee_id', $employee->id)->first();
        $this->assertTrue($attendance->date->isSameDay($date));
    }

    public function test_admin_can_forgive_lateness()
    {
        $employee = Employee::factory()->create();
        $date = Carbon::today();

        // CORRECCIÓN CLAVE:
        // Guardamos la fecha como string 'Y-m-d' explícitamente.
        // Esto asegura que cuando el controlador busque ['date' => '2025-11-29'],
        // encuentre este registro exacto y NO intente crear uno nuevo (duplicado).
        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'date' => $date->toDateString(), 
            'check_in' => '09:15:00',
            'incident_type' => IncidentType::ASISTENCIA,
            'is_late' => true,
        ]);

        $response = $this->post(route('payroll.update-day'), [
            'employee_id' => $employee->id,
            'date' => $date->toDateString(),
            'incident_type' => IncidentType::ASISTENCIA->value,
            'check_in' => '09:15',
            'late_ignored' => true, 
        ]);

        $response->assertRedirect();

        // Verificamos que se actualizó el registro existente
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id, // Debe ser el mismo ID
            'late_ignored' => 1,
            'is_late' => 1, // Este valor no se cambió en el controller, debe persistir
        ]);
        
        // Verificamos que NO se creó un duplicado
        $this->assertEquals(1, Attendance::where('employee_id', $employee->id)->count());
    }
}