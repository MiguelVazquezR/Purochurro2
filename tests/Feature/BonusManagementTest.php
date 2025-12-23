<?php

namespace Tests\Feature;

use App\Models\Bonus;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BonusManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_bonus_without_rules()
    {
        $response = $this->post(route('bonuses.store'), [
            'name' => 'Bono Simple',
            'amount' => 200.00,
            'type' => 'fixed',
            'description' => 'Otorgar sin reglas',
            'rule_config' => null, // Explícitamente sin reglas
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bonuses', [
            'name' => 'Bono Simple',
            'rule_config' => null
        ]);
    }

    public function test_can_create_punctuality_bonus_with_rules()
    {
        // Caso: Bono Puntualidad (Retardos <= 15 min acumulados en el periodo)
        $ruleData = [
            'concept' => 'late_minutes',      // Concepto: Retardos
            'operator' => '<=',               // Operador
            'value' => 15,                    // Cantidad
            'scope' => 'period_accumulated',  // Alcance: Acumulado del periodo
            'behavior' => 'fixed_amount'      // Comportamiento: Pagar monto fijo
        ];

        $response = $this->post(route('bonuses.store'), [
            'name' => 'Bono Puntualidad',
            'amount' => 500.00,
            'type' => 'fixed',
            'rule_config' => $ruleData
        ]);

        $response->assertRedirect();
        
        // Verificamos que se guardó el JSON correctamente
        $this->assertDatabaseHas('bonuses', [
            'name' => 'Bono Puntualidad',
            // En SQLite/MySQL testing, los JSON se guardan como string serializado
            'rule_config' => json_encode($ruleData), 
        ]);
    }

    public function test_can_create_extra_minutes_bonus_per_unit()
    {
        // Caso: Bono por Minutos Extra (Todo el excedente, por día, pagar por unidad)
        $ruleData = [
            'concept' => 'extra_minutes',
            'operator' => '>',
            'value' => 0,               // Todo lo mayor a 0
            'scope' => 'daily',         // Se evalúa por día
            'behavior' => 'pay_per_unit' // Se paga el 'amount' por cada minuto
        ];

        $response = $this->post(route('bonuses.store'), [
            'name' => 'Minutos Extra',
            'amount' => 5.00, // $5 pesos por minuto
            'type' => 'fixed',
            'rule_config' => $ruleData
        ]);

        $response->assertRedirect();
        
        $bonus = Bonus::where('name', 'Minutos Extra')->first();
        $this->assertEquals('pay_per_unit', $bonus->rule_config['behavior']);
        $this->assertEquals('daily', $bonus->rule_config['scope']);
    }

    public function test_validates_rule_structure_if_provided()
    {
        // Intentar enviar reglas incompletas
        $response = $this->post(route('bonuses.store'), [
            'name' => 'Bono Roto',
            'amount' => 100,
            'type' => 'fixed',
            'rule_config' => [
                'concept' => 'late_minutes',
                // Falta operador y valor
            ]
        ]);

        $response->assertSessionHasErrors(['rule_config.operator', 'rule_config.value']);
    }

    // Mantenemos el test original de asignación manual para asegurar retrocompatibilidad
    public function test_can_manually_assign_bonus_to_employee()
    {
        $employee = Employee::factory()->create();
        $bonus = Bonus::create([
            'name' => 'Bono Manual',
            'amount' => 500,
            'type' => 'fixed'
        ]);

        $date = Carbon::today()->format('Y-m-d');

        $response = $this->post(route('bonuses.assign'), [
            'employee_id' => $employee->id,
            'bonus_id' => $bonus->id,
            'assigned_date' => $date,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employee_bonus', [
            'employee_id' => $employee->id,
            'bonus_id' => $bonus->id,
        ]);
    }
}