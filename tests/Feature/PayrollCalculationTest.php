<?php

namespace Tests\Feature;

use App\Enums\IncidentType;
use App\Models\Attendance;
use App\Models\Bonus;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB; // Importante para fechas raw
use Tests\TestCase;

class PayrollCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_calculates_basic_weekly_salary()
    {
        // 1. Empleado con sueldo diario de $100
        $employee = Employee::factory()->create(['base_salary' => 100]);

        // 2. Crear asistencias para 7 días (Semana completa normal)
        $start = Carbon::parse('2025-01-01'); // Miércoles
        $end = $start->copy()->addDays(6); // Hasta Martes
        
        // Simulamos 6 días trabajados y 1 descanso
        for ($i = 0; $i < 7; $i++) {
            $date = $start->copy()->addDays($i);
            Attendance::create([
                'employee_id' => $employee->id,
                'date' => $date->format('Y-m-d'),
                'incident_type' => ($i == 6) ? IncidentType::DESCANSO : IncidentType::ASISTENCIA,
            ]);
        }

        // 3. Calcular
        $service = new PayrollService();
        $result = $service->calculate($employee, $start, $end);

        // 4. Verificar: 7 días * $100 = $700 (Asumiendo que el descanso se paga)
        $this->assertEquals(700, $result['total_pay']);
    }

    public function test_calculates_holidays_double_pay()
    {
        $employee = Employee::factory()->create(['base_salary' => 100]);
        $date = Carbon::parse('2025-05-01'); // Día festivo

        // Crear Feriado (Pago Doble = multiplier 2.0)
        // Usamos DB::table para evitar el problema de hora en SQLite si usáramos create
        DB::table('holidays')->insert([
            'name' => 'Día del Trabajo',
            'date' => $date->format('Y-m-d'),
            'pay_multiplier' => 2.0,
            'mandatory_rest' => true,
        ]);

        // El empleado trabajó ese día
        Attendance::create([
            'employee_id' => $employee->id,
            'date' => $date->format('Y-m-d'),
            'incident_type' => IncidentType::ASISTENCIA,
        ]);

        $service = new PayrollService();
        $result = $service->calculate($employee, $date, $date); // Solo un día

        // Esperamos: $100 * 2 = $200
        $this->assertEquals(200, $result['total_pay']);
    }

    public function test_deducts_unjustified_absences()
    {
        $employee = Employee::factory()->create(['base_salary' => 100]);
        $date = Carbon::parse('2025-06-01');

        Attendance::create([
            'employee_id' => $employee->id,
            'date' => $date->format('Y-m-d'),
            'incident_type' => IncidentType::FALTA_INJUSTIFICADA,
        ]);

        $service = new PayrollService();
        $result = $service->calculate($employee, $date, $date);

        $this->assertEquals(0, $result['total_pay']);
    }

    public function test_adds_bonuses_to_salary()
    {
        $employee = Employee::factory()->create(['base_salary' => 100]);
        $date = Carbon::parse('2025-07-01');

        // Asistencia Normal ($100)
        Attendance::create([
            'employee_id' => $employee->id,
            'date' => $date->format('Y-m-d'),
            'incident_type' => IncidentType::ASISTENCIA,
        ]);

        // Bono asignado ($50)
        $bonus = Bonus::create(['name' => 'Bono X', 'amount' => 50, 'type' => 'fixed']);
        $employee->bonuses()->attach($bonus->id, [
            'assigned_date' => $date->format('Y-m-d'),
            'amount' => 50
        ]);

        $service = new PayrollService();
        $result = $service->calculate($employee, $date, $date);

        // Esperamos: $100 (Sueldo) + $50 (Bono) = $150
        $this->assertEquals(150, $result['total_pay']);
    }
}