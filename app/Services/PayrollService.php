<?php

namespace App\Services;

use App\Enums\IncidentType;
use App\Models\Attendance;
use App\Models\Bonus;
use App\Models\Employee;
use App\Models\Holiday;
use Carbon\Carbon;

class PayrollService
{
    /**
     * Calcula la nómina de un empleado para un rango de fechas.
     */
    public function calculate(Employee $employee, Carbon $start, Carbon $end): array
    {
        $totalPay = 0;
        $totalDaysWorked = 0;
        $totalBonuses = 0;
        
        $details = [];

        // CORRECCIÓN: Usamos whereDate en lugar de whereBetween con strings
        // Esto asegura que '2025-01-01 00:00:00' se incluya al buscar '2025-01-01'
        
        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->get()
            ->keyBy(fn($a) => $a->date->format('Y-m-d'));

        $holidays = Holiday::whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->get()
            ->keyBy(fn($h) => $h->date->format('Y-m-d'));

        // Para pivotes es un poco distinto, pero wherePivotBetween suele fallar igual en SQLite
        // Lo cambiamos a una query más manual sobre el pivote o confiamos en que asignamos strings puros.
        // Dado que el test de bonos falló en la SUMA (no en encontrar el bono), 
        // el problema era que no encontró la asistencia para sumar el sueldo base.
        // Aun así, para consistencia, usamos wherePivot con callback si fuera necesario, 
        // pero por ahora el fallo principal eran las Asistencias.
        $bonuses = $employee->bonuses()
            ->wherePivotBetween('assigned_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get();

        // 2. Iterar día por día
        $current = $start->copy();
        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $attendance = $attendances->get($dateStr);
            $holiday = $holidays->get($dateStr);
            
            $dayPay = 0;
            $status = 'Normal';
            $isPayable = false;

            // Lógica de Pago por Día
            if ($attendance) {
                // Casos Pagados
                $payableIncidents = [
                    IncidentType::ASISTENCIA,
                    IncidentType::PERMISO_CON_GOCE,
                    IncidentType::VACACIONES,
                    IncidentType::DIA_FESTIVO,
                    IncidentType::DESCANSO,
                    IncidentType::INCAPACIDAD_TRABAJO
                ];

                if (in_array($attendance->incident_type, $payableIncidents)) {
                    $isPayable = true;
                    $dayPay = $employee->base_salary;
                    
                    if ($attendance->incident_type === IncidentType::ASISTENCIA) {
                        $totalDaysWorked++;
                    }
                }
            } else {
                $status = 'Sin Registro';
            }

            // Lógica de Días Feriados Trabajados
            if ($holiday && $attendance && $attendance->incident_type === IncidentType::ASISTENCIA) {
                // Si trabajó: Sueldo Base (ya sumado arriba) * (Multiplicador - 1) + Sueldo Base = Total Multiplicado
                // Ejemplo: Base 100, Multiplier 2.
                // Arriba sumamos 100.
                // Aquí calculamos: 100 * 2 = 200.
                // Reemplazamos el dayPay para ser exactos.
                
                $dayPay = $employee->base_salary * $holiday->pay_multiplier;
                $status = "Feriado Trabajado ({$holiday->name})";
            } elseif ($holiday && $isPayable) {
                 $status = "Feriado ({$holiday->name})";
                 // Si es feriado y descansó (y es pagable), suele pagarse sencillo (ya sumado) 
                 // o doble según ley local. Por ahora lo dejamos sencillo como 'payable'.
            }

            $totalPay += $dayPay;
            
            if ($dayPay > 0) {
                $details['days'][] = [
                    'date' => $dateStr,
                    'amount' => $dayPay,
                    'concept' => $status,
                    'incident' => $attendance?->incident_type->label() ?? 'N/A'
                ];
            }

            $current->addDay();
        }

        // 3. Sumar Bonos
        foreach ($bonuses as $bonus) {
            $amount = $bonus->pivot->amount;
            $totalPay += $amount;
            $totalBonuses += $amount;
            
            $details['bonuses'][] = [
                'name' => $bonus->name,
                'amount' => $amount,
                'date' => $bonus->pivot->assigned_date
            ];
        }

        return [
            'employee' => $employee->only(['id', 'first_name', 'last_name', 'base_salary']),
            'total_pay' => round($totalPay, 2),
            'days_worked' => $totalDaysWorked,
            'total_bonuses' => round($totalBonuses, 2),
            'breakdown' => $details
        ];
    }
}