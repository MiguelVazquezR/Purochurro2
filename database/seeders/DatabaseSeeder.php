<?php

namespace Database\Seeders;

use App\Enums\IncidentType;
use App\Models\Attendance;
use App\Models\Bonus;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear Turnos
        $shiftMorning = Shift::create([
            'name' => 'Matutino',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'color' => '#3b82f6', // Azul
            'is_active' => true,
        ]);

        $shiftEvening = Shift::create([
            'name' => 'Vespertino',
            'start_time' => '14:00',
            'end_time' => '22:00',
            'color' => '#f97316', // Naranja
            'is_active' => true,
        ]);

        // 2. Crear Bonos
        $bonusPunctuality = Bonus::create([
            'name' => 'Bono de Puntualidad',
            'description' => 'Cero retardos en la quincena',
            'amount' => 500.00,
            'type' => 'fixed',
            'is_active' => true,
            // Ejemplo de regla automática (si implementaste la lógica)
            'rule_config' => [
                'concept' => 'late_minutes',
                'operator' => '<=',
                'value' => 0,
                'scope' => 'period_accumulated',
                'behavior' => 'fixed_amount'
            ]
        ]);

        Bonus::create([
            'name' => 'Comisión Ventas',
            'description' => 'Porcentaje sobre ventas individuales',
            'amount' => 5.00,
            'type' => 'percentage',
            'is_active' => true,
        ]);

        // 3. Crear Días Festivos (Ejemplo: Navidad y Año Nuevo)
        Holiday::create([
            'name' => 'Navidad',
            'date' => Carbon::create(null, 12, 25), // Año actual
            'mandatory_rest' => true,
            'pay_multiplier' => 2.0,
        ]);

        // 4. Crear Admin / Empleado Principal
        // Usuario ID: 1
        $adminUser = User::create([
            'name' => 'Administrador',
            'email' => 'admin@purochurro.com',
            'password' => Hash::make('password'),
        ]);

        // Empleado ligado al Admin (opcional, pero útil si el admin checa)
        // $adminEmployee = Employee::create([
        //     'user_id' => $adminUser->id,
        //     'first_name' => 'Admin',
        //     'last_name' => 'System',
        //     'birth_date' => '1990-01-01',
        //     'phone' => '3300000000',
        //     'address' => 'Oficina Central',
        //     'email' => 'admin@purochurro.com',
        //     'hired_at' => '2020-01-01',
        //     'base_salary' => 5000.00, // Semanal
        //     'vacation_balance' => 12,
        //     'is_active' => true,
        // ]);

        // 5. Crear Empleados de Prueba
        $employeesData = [
            ['Juan', 'Pérez', 'juan@test.com', 2500],
            ['María', 'González', 'maria@test.com', 2800],
            ['Carlos', 'López', 'carlos@test.com', 2200],
        ];

        foreach ($employeesData as $idx => $data) {
            $user = User::create([
                'name' => "$data[0] $data[1]",
                'email' => $data[2],
                'password' => Hash::make('password'), // Password genérico
            ]);

            $employee = Employee::create([
                'user_id' => $user->id,
                'first_name' => $data[0],
                'last_name' => $data[1],
                'birth_date' => '1995-05-15',
                'phone' => '331234567' . $idx,
                'address' => 'Domicilio Conocido ' . $idx,
                'email' => $data[2],
                'hired_at' => Carbon::now()->subYears(2),
                'base_salary' => $data[3],
                'vacation_balance' => 6,
                'is_active' => true,
            ]);

            // Asignar el bono de puntualidad a todos
            $employee->bonuses()->attach($bonusPunctuality->id, [
                'assigned_date' => Carbon::now(),
                'amount' => $bonusPunctuality->amount
            ]);

            // 6. Generar Asistencias y Horarios para la Semana Actual
            $startOfWeek = Carbon::now()->startOfWeek();
            
            // Simulamos datos para los últimos 5 días
            for ($i = 0; $i < 5; $i++) {
                $date = $startOfWeek->copy()->addDays($i);
                
                // Asignar Horario (WorkSchedule)
                WorkSchedule::create([
                    'employee_id' => $employee->id,
                    'shift_id' => $shiftMorning->id,
                    'date' => $date,
                    'is_published' => true,
                ]);

                // Simular Asistencia (Solo si la fecha es hoy o anterior)
                if ($date <= Carbon::now()) {
                    $checkIn = '09:00:00';
                    $checkOut = '17:00:00';
                    $incident = IncidentType::ASISTENCIA;
                    $isLate = false;

                    // Escenarios aleatorios para probar la vista de Nómina
                    if ($idx == 0 && $i == 1) { // Juan llega tarde el martes
                        $checkIn = '09:45:00';
                        $isLate = true;
                    }
                    if ($idx == 1 && $i == 2) { // María falta el miércoles
                        $checkIn = null;
                        $checkOut = null;
                        $incident = IncidentType::FALTA_INJUSTIFICADA;
                    }

                    if ($incident === IncidentType::ASISTENCIA) {
                        Attendance::create([
                            'employee_id' => $employee->id,
                            'date' => $date,
                            'check_in' => $checkIn,
                            'check_out' => $checkOut,
                            'incident_type' => $incident,
                            'is_late' => $isLate,
                        ]);
                    } else {
                        Attendance::create([
                            'employee_id' => $employee->id,
                            'date' => $date,
                            'incident_type' => $incident,
                        ]);
                    }
                }
            }
        }
    }
}