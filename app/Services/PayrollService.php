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
     * Retorna una estructura detallada para la generación de recibos.
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

        // SOLO cargamos bonos recurrentes activos
        $activeBonuses = $employee->recurringBonuses()
            ->where('bonuses.is_active', true)
            ->wherePivot('is_active', true)
            ->get();

        // 2. Inicializar Acumuladores
        $totalPay = 0;
        $totalBonuses = 0;
        
        // Contadores de DÍAS/INCIDENCIAS
        $counters = [
            'days_worked' => 0,       // Días normales trabajados
            'holidays_worked' => 0,   // Festivos laborados
            'holidays_rest' => 0,     // Festivos descansados (pagados)
            'vacations' => 0,
            'absences' => 0,     
            'lates' => 0,        
            'incapacity' => 0,   
            'permissions' => 0,
        ];

        // Desglose de DINERO por categoría (Para el recibo)
        $moneyBreakdown = [
            'salary_normal' => 0,     // Pago por días normales
            'salary_holidays' => 0,   // Pago por festivos (trabajados o no)
            'salary_vacations' => 0,  // Pago por vacaciones
            'salary_other' => 0,      // Incapacidades, permisos pagados, etc.
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
            $dayCategory = 'normal'; // Categoría para agrupación en recibo: normal, holiday, vacation, other
            
            $isPayable = false;
            
            // Métricas del día para reglas
            $dayLateMins = 0;
            $dayExtraMins = 0;
            $dayIsAbsent = false;
            $dayIsAttendance = false;

            if ($attendance) {
                // Validación de horario y retardos
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
                        $dayLateMins = 0;
                    }
                }
                
                $dayExtraMins = ($attendance->extra_hours ?? 0) * 60;

                if ($attendance->is_late && !$attendance->late_ignored) {
                    $counters['lates']++;
                }

                // Switch principal de incidencias
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
                        $dayCategory = 'vacation';
                        break;
                    case IncidentType::DIA_FESTIVO:
                        // Si se marca explícitamente como festivo en asistencia (raro si es automático, pero posible)
                        $isPayable = true;
                        $counters['holidays_rest']++;
                        $status = 'Día Festivo';
                        $dayCategory = 'holiday';
                        break;
                    case IncidentType::INCAPACIDAD_TRABAJO:
                    case IncidentType::INCAPACIDAD_GENERAL:
                        $isPayable = true;
                        $counters['incapacity']++;
                        $status = 'Incapacidad';
                        $dayCategory = 'other';
                        break;
                    case IncidentType::PERMISO_CON_GOCE:
                        $isPayable = true;
                        $status = 'Permiso con Goce';
                        $dayCategory = 'other';
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
                        // El descanso normalmente es parte del sueldo normal semanal
                        break;
                }
            } else {
                // Sin registro de asistencia
                $status = 'Sin Registro';
                if ($schedule && $schedule->shift_id) {
                    $dayIsAbsent = true; 
                }
            }

            // --- CÁLCULO DE PAGO DEL DÍA ---
            
            // 1. Pago Base Ordinario
            if ($isPayable) {
                $dayPay = $employee->base_salary;
            }

            // 2. Lógica Específica de Festivos (Sobreescribe o ajusta)
            if ($holiday) {
                // Caso A: Festivo Laborado (Asistencia en día festivo)
                if ($attendance && $attendance->incident_type === IncidentType::ASISTENCIA) {
                    $multiplier = $holiday->pay_multiplier; // Ej: 3.0
                    $dayPay = $employee->base_salary * $multiplier;
                    
                    $counters['holidays_worked']++;
                    // Restamos del contador de 'normales' porque ya se cuenta aquí como festivo trabajado
                    $counters['days_worked']--; 
                    
                    $status = "Feriado Laborado ({$holiday->name})";
                    $dayCategory = 'holiday';
                } 
                // Caso B: Festivo Descansado (Pagado)
                elseif ($isPayable && ($attendance->incident_type !== IncidentType::ASISTENCIA)) {
                     // Ya se sumó el pago base arriba, solo ajustamos etiquetas
                     $status = "Feriado ({$holiday->name})";
                     $dayCategory = 'holiday';
                     
                     // Si la incidencia no era explícitamente DIA_FESTIVO, ajustamos contadores
                     // (Ej: Era DESCANSO pero cayó en feriado)
                     if ($attendance->incident_type !== IncidentType::DIA_FESTIVO) {
                         $counters['holidays_rest']++;
                         // No restamos de days_worked porque descanso no suma a days_worked
                     }
                }
            }

            $totalPay += $dayPay;

            // Acumular dinero en su categoría para el recibo
            if ($dayPay > 0) {
                switch ($dayCategory) {
                    case 'holiday':
                        $moneyBreakdown['salary_holidays'] += $dayPay;
                        break;
                    case 'vacation':
                        $moneyBreakdown['salary_vacations'] += $dayPay;
                        break;
                    case 'other':
                        $moneyBreakdown['salary_other'] += $dayPay;
                        break;
                    default:
                        $moneyBreakdown['salary_normal'] += $dayPay;
                        break;
                }
            }
            
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

            // Detalle para el recibo visual (lista de días)
            if ($dayPay > 0 || $attendance) {
                $details['days'][] = [
                    'date' => $dateStr,
                    'amount' => $dayPay,
                    'concept' => $status,
                    'incident' => $attendance?->incident_type->label() ?? 'N/A',
                    'category' => $dayCategory, // Útil para agrupar en Vue
                    'is_holiday_worked' => ($dayCategory === 'holiday' && ($attendance && $attendance->incident_type === IncidentType::ASISTENCIA)),
                    'is_holiday_rest' => ($dayCategory === 'holiday' && (!$attendance || $attendance->incident_type !== IncidentType::ASISTENCIA)),
                ];
            }

            $current->addDay();
        }

        // 4. Calcular Bonos (SOLO RECURRENTES ACTIVOS)
        foreach ($activeBonuses as $bonus) {
            $baseAmount = $bonus->pivot->amount ?? $bonus->amount;
            $config = $bonus->rule_config;

            // CASO A: Bono Incondicional
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
            
            // B.1 Evaluación Diaria
            if (isset($config['scope']) && $config['scope'] === 'daily') {
                foreach ($dailyStats as $date => $stat) {
                    if ($config['concept'] === 'attendance' && !$stat['is_attendance']) continue;
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
            // B.2 Evaluación Global
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
        
        // Total de días pagados que implican trabajo real (Normales + Festivos Laborados)
        $totalDaysWorked = $counters['days_worked'] + $counters['holidays_worked'];

        return [
            'employee' => $employee->only(['id', 'first_name', 'last_name', 'base_salary']),
            'total_pay' => round($totalPay, 2),
            'days_worked' => $totalDaysWorked,
            'total_bonuses' => round($totalBonuses, 2),
            // 'totals_breakdown' es clave para mostrar resumen en el recibo
            'totals_breakdown' => $moneyBreakdown,
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