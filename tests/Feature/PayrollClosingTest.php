<?php

namespace Tests\Feature;

use App\Enums\IncidentType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PayrollReceipt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollClosingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_close_payroll_and_generate_receipts()
    {
        // 1. Preparar escenario: 2 empleados
        $employee1 = Employee::factory()->create(['base_salary' => 100]);
        $employee2 = Employee::factory()->create(['base_salary' => 200]);

        $start = Carbon::parse('2025-02-02'); // Domingo (Inicio semana)
        
        // Empleado 1 trabajó 1 día
        Attendance::create([
            'employee_id' => $employee1->id,
            'date' => $start->format('Y-m-d'),
            'incident_type' => IncidentType::ASISTENCIA,
        ]);

        // Empleado 2 no tiene registros (0 pago)

        // 2. Ejecutar Cierre (POST)
        $response = $this->post(route('payroll.store-settlement'), [
            'start_date' => $start->format('Y-m-d'),
            'mark_as_paid' => true,
        ]);

        $response->assertRedirect(route('payroll.index'));

        // 3. Verificaciones
        $this->assertDatabaseCount('payroll_receipts', 2);

        // Verificar Recibo Empleado 1
        $receipt1 = PayrollReceipt::where('employee_id', $employee1->id)->first();
        $this->assertEquals(100, $receipt1->total_pay); // 1 día * $100
        $this->assertEquals(100, $receipt1->base_salary_snapshot);
        $this->assertNotNull($receipt1->paid_at);
        
        // Verificar que el breakdown se guardó como JSON (array al leerlo por el cast)
        $this->assertIsArray($receipt1->breakdown_data);
        $this->assertNotEmpty($receipt1->breakdown_data['days']); // Debe tener el detalle del día trabajado

        // Verificar Recibo Empleado 2 (En ceros)
        $receipt2 = PayrollReceipt::where('employee_id', $employee2->id)->first();
        $this->assertEquals(0, $receipt2->total_pay);
    }

    public function test_cannot_close_same_payroll_twice()
    {
        $employee = Employee::factory()->create();
        $start = Carbon::parse('2025-02-02');

        // Primer cierre
        $this->post(route('payroll.store-settlement'), [
            'start_date' => $start->format('Y-m-d'),
        ]);

        // Segundo cierre (Misma fecha)
        $response = $this->post(route('payroll.store-settlement'), [
            'start_date' => $start->format('Y-m-d'),
        ]);

        // Debe fallar o redirigir con error
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('payroll_receipts', 1); // No duplicó
    }
}