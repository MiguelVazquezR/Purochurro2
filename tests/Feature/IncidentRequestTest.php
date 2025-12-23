<?php

namespace Tests\Feature;

use App\Enums\IncidentType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\IncidentRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_create_request()
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        
        $this->actingAs($user);

        $response = $this->post(route('incident-requests.store'), [
            'incident_type' => IncidentType::VACACIONES->value,
            'start_date' => Carbon::tomorrow()->format('Y-m-d'),
            'end_date' => Carbon::tomorrow()->addDay()->format('Y-m-d'),
            'employee_reason' => 'Necesito descansar',
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('incident_requests', [
            'employee_id' => $employee->id,
            'status' => 'pending',
            'employee_reason' => 'Necesito descansar'
        ]);
    }

    public function test_admin_can_reject_request()
    {
        $admin = User::factory()->create(); 
        $employee = Employee::factory()->create();
        $request = IncidentRequest::create([
            'employee_id' => $employee->id,
            'incident_type' => IncidentType::PERMISO_SIN_GOCE,
            'start_date' => '2025-05-01',
            'end_date' => '2025-05-01',
            'status' => 'pending'
        ]);

        $this->actingAs($admin);

        $response = $this->patch(route('incident-requests.update-status', $request), [
            'status' => 'rejected',
            'admin_response' => 'No hay personal suficiente',
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('incident_requests', [
            'id' => $request->id,
            'status' => 'rejected',
            'admin_response' => 'No hay personal suficiente',
        ]);

        $this->assertDatabaseMissing('attendances', [
            'employee_id' => $employee->id,
            'date' => '2025-05-01'
        ]);
    }

    public function test_admin_approval_creates_attendance_and_deducts_vacation()
    {
        $admin = User::factory()->create(); 
        $employee = Employee::factory()->create(['vacation_balance' => 10.0]);
        
        $start = Carbon::parse('2025-06-01');
        $end = Carbon::parse('2025-06-02'); // 2 días

        $request = IncidentRequest::create([
            'employee_id' => $employee->id,
            'incident_type' => IncidentType::VACACIONES,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'status' => 'pending'
        ]);

        $this->actingAs($admin);

        $this->patch(route('incident-requests.update-status', $request), [
            'status' => 'approved',
        ]);

        // 1. Verificar estado solicitud
        $this->assertDatabaseHas('incident_requests', ['id' => $request->id, 'status' => 'approved']);

        // 2. CORRECCIÓN: Usamos whereDate para buscar la asistencia
        // Esto evita el error de "2025-06-01" vs "2025-06-01 00:00:00" en SQLite
        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $start->format('Y-m-d'))
            ->first();

        $this->assertNotNull($attendance, 'No se creó la asistencia para el día 1');
        $this->assertEquals(IncidentType::VACACIONES, $attendance->incident_type);
        
        // 3. Verificar Descuento de Vacaciones
        $employee->refresh();
        $this->assertEquals(8.0, $employee->vacation_balance);
        
        // 4. Verificar Log de Vacaciones
        $this->assertDatabaseHas('vacation_logs', [
            'employee_id' => $employee->id,
            'type' => 'usage',
            'days' => -2,
        ]);
    }
}