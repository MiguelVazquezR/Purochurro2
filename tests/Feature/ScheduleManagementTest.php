<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Login
        $this->actingAs(User::factory()->create([
            'email_verified_at' => now(),
        ]));
    }

    public function test_can_view_schedule_page()
    {
        $response = $this->get(route('schedule.index'));
        $response->assertStatus(200);
    }

    public function test_can_assign_single_day_shift()
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create(['name' => 'Matutino']);
        $date = Carbon::tomorrow();

        $response = $this->post(route('schedule.store'), [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'date' => $date->format('Y-m-d'),
            'notes' => 'Cambio manual',
        ]);

        $response->assertRedirect();
        
        // CORRECCIÓN: Al usar SQLite en testing, la fecha se guarda a veces como datetime.
        // Verificamos que exista el registro ignorando el formato exacto del string de fecha
        // O buscamos usando Eloquent que sí maneja el cast.
        
        $this->assertDatabaseHas('work_schedules', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            // 'date' => $date->format('Y-m-d'), // Esto falla en SQLite
            'notes' => 'Cambio manual',
        ]);

        // Verificación extra más robusta usando el modelo (que sí usa casts)
        $schedule = WorkSchedule::where('employee_id', $employee->id)->first();
        $this->assertTrue($schedule->date->isSameDay($date));
    }

    public function test_can_generate_weekly_schedule_from_template()
    {
        // 1. Crear Turnos
        $shiftMorning = Shift::factory()->create(['name' => 'Matutino']);
        $shiftEvening = Shift::factory()->create(['name' => 'Vespertino']);

        // 2. Crear Empleado con Plantilla usando IDs de turnos
        $employee = Employee::factory()->create([
            'default_schedule_template' => [
                'monday' => $shiftMorning->id,
                'tuesday' => $shiftMorning->id,
                'wednesday' => null, // Descanso
                'thursday' => $shiftEvening->id,
                'friday' => $shiftEvening->id,
            ]
        ]);

        // 3. Definir fecha de inicio de semana (Lunes)
        $nextMonday = Carbon::now()->next('Monday');

        // 4. Ejecutar Generación
        $response = $this->post(route('schedule.generate'), [
            'start_date' => $nextMonday->format('Y-m-d'),
        ]);

        $response->assertRedirect();

        // 5. Verificaciones
        // Lunes: Debe tener turno matutino
        // Usamos whereDate para que la DB se encargue de comparar solo la fecha
        // y no falle por las horas 00:00:00 vs string simple.
        
        $mondaySchedule = WorkSchedule::where('employee_id', $employee->id)
            ->whereDate('date', $nextMonday->format('Y-m-d'))
            ->first();

        $this->assertNotNull($mondaySchedule, 'El horario del lunes no se creó');
        $this->assertEquals($shiftMorning->id, $mondaySchedule->shift_id);

        // Jueves: Vespertino
        $thursdayDate = $nextMonday->copy()->addDays(3);
        $thursdaySchedule = WorkSchedule::where('employee_id', $employee->id)
            ->whereDate('date', $thursdayDate->format('Y-m-d'))
            ->first();

        $this->assertNotNull($thursdaySchedule, 'El horario del jueves no se creó');
        $this->assertEquals($shiftEvening->id, $thursdaySchedule->shift_id);

        // Miércoles: Descanso (shift_id debe ser null o registro creado con null)
        $wednesdayDate = $nextMonday->copy()->addDays(2);
        $wednesdaySchedule = WorkSchedule::where('employee_id', $employee->id)
            ->whereDate('date', $wednesdayDate->format('Y-m-d'))
            ->first();
            
        // Dependiendo de tu lógica, puede que se cree con NULL o no se cree.
        // Tu controlador actual hace updateOrCreate con shift_id = null, así que debe existir.
        $this->assertNotNull($wednesdaySchedule, 'El registro del miércoles no existe');
        $this->assertNull($wednesdaySchedule->shift_id, 'El miércoles debería ser descanso (null)');
    }
}