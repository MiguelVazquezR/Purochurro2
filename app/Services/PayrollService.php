<?php

namespace App\Services;

use App\Enums\IncidentType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\WorkSchedule;
use Carbon\Carbon;

class PayrollService
{
    /**
     * Calcula la nómina interpretando estrictamente las reglas configuradas.
     */
    public function calculate(Employee $employee, Carbon $start, Carbon $end): array
    {
        // 1. Preparar Datos
        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->get()
            ->keyBy(fn($a) => $a->date->format('Y-m-d'));

        $holidays = Holiday::whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->get()
            ->keyBy(fn($h) => $h->date->format('Y-m-d'));

        $schedules = WorkSchedule::with('shift')
            ->where('employee_id', $employee->id)
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->get()
            ->keyBy(fn($s) => $s->date->format('Y-m-d'));

        // SOLO cargamos bonos recurrentes activos (Configuración)
        // Eliminamos la lectura de bonos manuales históricos (pivot employee_bonus)
        $activeBonuses = $employee->recurringBonuses()
            ->where('bonuses.is_active', true)
            ->wherePivot('is_active', true)
            ->get();

        // 2. Inicializar Acumuladores
        $totalPay = 0;
        $totalBonuses = 0;
        
        $counters = [
            'days_worked' => 0,
            'holidays_worked' => 0,
            'holidays_rest' => 0,
            'vacations' => 0,
            'absences' => 0,     
            'lates' => 0,        
            'incapacity' => 0,   
            'permissions' => 0,
        ];

        $periodStats = [
            'late_minutes' => 0,
            'extra_minutes' => 0,
            'unjustified_absences' => 0,
            'attendance_days' => 0,
        ];
        
        $dailyStats = []; 
        $details = ['days' => [], 'bonuses' => []];

        // 3. Procesar Días (Sueldo Base + Estadísticas para Reglas)
        $current = $start->copy();
        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $attendance = $attendances->get($dateStr);
            $schedule = $schedules->get($dateStr);
            $holiday = $holidays->get($dateStr);
            
            $dayPay = 0;
            $status = 'Normal';
            $isPayable = false;
            
            // Métricas del día para reglas
            $dayLateMins = 0;
            $dayExtraMins = 0;
            $dayIsAbsent = false;
            $dayIsAttendance = false;

            if ($attendance) {
                // CORRECCIÓN DE FECHA DOBLE: Extraer H:i:s explícitamente
                if ($attendance->check_in && $schedule?->shift) {
                    try {
                        $shiftTimeStr = Carbon::parse($schedule->shift->start_time)->format('H:i:s');
                        $checkInTimeStr = Carbon::parse($attendance->check_in)->format('H:i:s');

                        $shiftStart = Carbon::parse($dateStr . ' ' . $shiftTimeStr);
                        $checkIn = Carbon::parse($dateStr . ' ' . $checkInTimeStr);
                        
                        if ($checkIn->gt($shiftStart)) {
                            $dayLateMins = $checkIn->diffInMinutes($shiftStart);
                            if ($attendance->late_ignored) {
                                $dayLateMins = 0; // Perdonado
                            }
                        }
                    } catch (\Exception $e) {
                        // Fallback silencioso o log si el formato de hora es inválido
                        $dayLateMins = 0;
                    }
                }
                
                $dayExtraMins = ($attendance->extra_hours ?? 0) * 60;

                if ($attendance->is_late && !$attendance->late_ignored) {
                    $counters['lates']++;
                }

                switch ($attendance->incident_type) {
                    case IncidentType::ASISTENCIA:
                        $isPayable = true;
                        $dayIsAttendance = true;
                        $counters['days_worked']++;
                        break;
                    case IncidentType::VACACIONES:
                        $isPayable = true;
                        $counters['vacations']++;
                        $status = 'Vacaciones';
                        break;
                    case IncidentType::DIA_FESTIVO:
                        $isPayable = true;
                        $counters['holidays_rest']++;
                        $status = 'Día Festivo';
                        break;
                    case IncidentType::INCAPACIDAD_TRABAJO:
                    case IncidentType::INCAPACIDAD_GENERAL:
                        $isPayable = true;
                        $counters['incapacity']++;
                        $status = 'Incapacidad';
                        break;
                    case IncidentType::PERMISO_CON_GOCE:
                        $isPayable = true;
                        $status = 'Permiso con Goce';
                        break;
                    case IncidentType::FALTA_INJUSTIFICADA:
                        $counters['absences']++;
                        $dayIsAbsent = true;
                        $status = 'Falta Injustificada';
                        break;
                    case IncidentType::PERMISO_SIN_GOCE:
                        $counters['permissions']++;
                        $status = 'Permiso sin Goce';
                        break;
                    case IncidentType::DESCANSO:
                        $isPayable = true; 
                        $status = 'Descanso';
                        break;
                }
            } else {
                $status = 'Sin Registro';
                if ($schedule && $schedule->shift_id) {
                    $dayIsAbsent = true; // Tenía turno y no hay registro
                }
            }

            // Pago Base
            if ($isPayable) {
                $dayPay = $employee->base_salary;
            }

            // Festivos Trabajados
            if ($holiday && $attendance && $attendance->incident_type === IncidentType::ASISTENCIA) {
                $multiplier = $holiday->pay_multiplier;
                $dayPay = $employee->base_salary * $multiplier;
                $counters['holidays_worked']++;
                $counters['days_worked']--; 
                $status = "Feriado Trabajado ({$holiday->name})";
            } 
            elseif ($holiday && $isPayable && $attendance && $attendance->incident_type !== IncidentType::ASISTENCIA) {
                 $status = "Feriado ({$holiday->name})";
            }

            $totalPay += $dayPay;
            
            // Guardar Stats
            $dailyStats[$dateStr] = [
                'late_minutes' => $dayLateMins,
                'extra_minutes' => $dayExtraMins,
                'is_absent' => $dayIsAbsent,
                'is_attendance' => $dayIsAttendance,
            ];

            $periodStats['late_minutes'] += $dayLateMins;
            $periodStats['extra_minutes'] += $dayExtraMins;
            if ($dayIsAbsent) $periodStats['unjustified_absences']++;
            if ($dayIsAttendance) $periodStats['attendance_days']++;

            if ($dayPay > 0 || $attendance) {
                $details['days'][] = [
                    'date' => $dateStr,
                    'amount' => $dayPay,
                    'concept' => $status,
                    'incident' => $attendance?->incident_type->label() ?? 'N/A'
                ];
            }

            $current->addDay();
        }

        // 4. Calcular Bonos (SOLO RECURRENTES ACTIVOS)
        foreach ($activeBonuses as $bonus) {
            $baseAmount = $bonus->pivot->amount ?? $bonus->amount;
            $config = $bonus->rule_config;

            // CASO A: Bono Incondicional (Sin reglas) -> Se paga siempre (1 vez por periodo)
            if (empty($config)) {
                $totalPay += $baseAmount;
                $totalBonuses += $baseAmount;
                $details['bonuses'][] = [
                    'name' => $bonus->name,
                    'amount' => $baseAmount,
                    'type' => 'recurring'
                ];
                continue;
            }

            // CASO B: Bono con Reglas
            $bonusTotal = 0;
            
            // B.1 Evaluación Diaria (Acumula por día cumplido)
            if (isset($config['scope']) && $config['scope'] === 'daily') {
                foreach ($dailyStats as $date => $stat) {
                    // Si la regla es sobre asistencia, ignoramos días que no vino o no le tocaba
                    if ($config['concept'] === 'attendance' && !$stat['is_attendance']) continue;
                    
                    // Si la regla es sobre retardos, solo evaluamos días que asistió
                    if ($config['concept'] === 'late_minutes' && !$stat['is_attendance']) continue;

                    $valueToEvaluate = match($config['concept']) {
                        'late_minutes' => $stat['late_minutes'],
                        'extra_minutes' => $stat['extra_minutes'],
                        'attendance' => $stat['is_attendance'] ? 1 : 0,
                        default => 0
                    };

                    if ($this->checkRule($valueToEvaluate, $config['operator'], $config['value'])) {
                        if ($config['behavior'] === 'fixed_amount') {
                            $bonusTotal += $baseAmount;
                        } elseif ($config['behavior'] === 'pay_per_unit') {
                             $bonusTotal += ($valueToEvaluate * $baseAmount);
                        }
                    }
                }
            } 
            // B.2 Evaluación Global del Periodo
            else {
                $valueToEvaluate = match($config['concept'] ?? '') {
                    'late_minutes' => $periodStats['late_minutes'],
                    'extra_minutes' => $periodStats['extra_minutes'],
                    'unjustified_absences' => $periodStats['unjustified_absences'],
                    'attendance' => $periodStats['attendance_days'],
                    default => 0
                };

                if ($this->checkRule($valueToEvaluate, $config['operator'], $config['value'])) {
                    if ($config['behavior'] === 'fixed_amount') {
                        $bonusTotal += $baseAmount;
                    } 
                    elseif ($config['behavior'] === 'pay_per_unit') {
                        // Ej: > 15 mins extra. Pagamos excedente.
                        $threshold = ($config['operator'] === '>') ? $config['value'] : 0;
                        $unitsToPay = max(0, $valueToEvaluate - $threshold);
                        $bonusTotal += ($unitsToPay * $baseAmount);
                    }
                }
            }

            if ($bonusTotal > 0) {
                $totalPay += $bonusTotal;
                $totalBonuses += $bonusTotal;
                $details['bonuses'][] = [
                    'name' => $bonus->name,
                    'amount' => $bonusTotal,
                    'type' => 'recurring_rule'
                ];
            }
        }
        
        $totalDaysWorked = $counters['days_worked'] + $counters['holidays_worked'];

        return [
            'employee' => $employee->only(['id', 'first_name', 'last_name', 'base_salary']),
            'total_pay' => round($totalPay, 2),
            'days_worked' => $totalDaysWorked,
            'total_bonuses' => round($totalBonuses, 2),
            'breakdown' => array_merge($details, $counters)
        ];
    }

    private function checkRule($actual, $operator, $target): bool
    {
        return match($operator) {
            '=' => $actual == $target,
            '>' => $actual > $target,
            '<' => $actual < $target,
            '>=' => $actual >= $target,
            '<=' => $actual <= $target,
            default => false
        };
    }
}