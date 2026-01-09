<?php

namespace App\Services;

use App\Enums\IncidentType;
use App\Models\Attendance;
use App\Models\DailyOperation;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Product;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use App\Services\BonusService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; 

class PayrollService
{
    protected BonusService $bonusService;

    public function __construct()
    {
        $this->bonusService = new BonusService();
    }

    public function calculate(Employee $employee, Carbon $start, Carbon $end): array
    {
        // 1. Preparar Datos
        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->get()
            ->keyBy(fn($a) => $a->date->format('Y-m-d'));

        // Obtener días festivos ignorando el año
        $months = [$start->month, $end->month];
        $months = array_unique($months);

        $holidays = Holiday::whereIn(DB::raw('MONTH(date)'), $months)
            ->get()
            ->keyBy(fn($h) => $h->date->format('m-d'));

        // Cargamos los horarios para saber si le tocaba trabajar
        $schedules = WorkSchedule::with('shift')
            ->where('employee_id', $employee->id)
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->get()
            ->keyBy(fn($s) => $s->date->format('Y-m-d'));

        $dailyOperations = DailyOperation::with('sales')
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->get()
            ->keyBy(fn($d) => $d->date->format('Y-m-d'));

        // 2. Inicializar Acumuladores
        $totalPay = 0;
        $totalCommissions = 0;

        $counters = [
            'days_worked' => 0,
            'holidays_worked' => 0,
            'holidays_rest' => 0,
            'vacations' => 0,
            'absences' => 0,
            'lates' => 0,
            'incapacity' => 0,
            'permissions' => 0,
            'commissions_count' => 0,
            'double_shifts' => 0,
        ];

        $moneyBreakdown = [
            'salary_normal' => 0,
            'salary_holidays_worked' => 0,
            'salary_holidays_rest' => 0,
            'salary_vacations' => 0,
            'salary_incapacity' => 0,
            'salary_permissions' => 0,
            'salary_other' => 0,
            'commissions' => 0,
        ];

        $periodStats = [
            'late_minutes' => 0,
            'extra_minutes' => 0,
            'unjustified_absences' => 0,
            'attendance_days' => 0,
        ];

        $dailyStats = [];
        $details = ['days' => [], 'bonuses' => [], 'commissions' => []];

        // 3. Procesar Días
        $current = $start->copy();
        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $monthDay = $current->format('m-d');
            
            $attendance = $attendances->get($dateStr);
            $schedule = $schedules->get($dateStr); 
            $holiday = $holidays->get($monthDay); 
            $operation = $dailyOperations->get($dateStr);

            $dayPay = 0;
            $status = 'Normal';
            $dayCategory = 'normal';
            $isPayable = false;

            $dayLateMins = 0;
            $dayExtraMins = 0;
            $dayIsAbsent = false;
            $dayIsAttendance = false;
            
            // Variable clave para bonos por turno
            $dayWorkedMinutes = 0; 

            $shiftsCount = 1;

            if ($attendance) {
                // --- Lógica si ASISTIÓ ---
                if ($attendance->check_in && $attendance->check_out) {
                    try {
                        $in = Carbon::parse($attendance->check_in);
                        $out = Carbon::parse($attendance->check_out);
                        
                        // Corrección paso de medianoche
                        if ($out->lessThan($in)) $out->addDay();

                        // Calculamos minutos exactos trabajados
                        $dayWorkedMinutes = $in->diffInMinutes($out);

                        // Regla: A partir de 9 horas (540 minutos) son 2 turnos
                        if ($dayWorkedMinutes >= 540) {
                            $shiftsCount = 2;
                            $counters['double_shifts']++;
                        }
                    } catch (\Exception $e) {}
                }

                if ($attendance->check_in && $schedule?->shift) {
                    try {
                        $shiftTimeStr = Carbon::parse($schedule->shift->start_time)->format('H:i:s');
                        $checkInTimeStr = Carbon::parse($attendance->check_in)->format('H:i:s');
                        
                        $shiftStart = Carbon::parse("{$dateStr} {$shiftTimeStr}");
                        $checkIn = Carbon::parse("{$dateStr} {$checkInTimeStr}");
                        if ($checkIn->gt($shiftStart)) {
                            $dayLateMins = $attendance->late_ignored ? 0 : $shiftStart->diffInMinutes($checkIn, true);
                        }
                    } catch (\Exception $e) {}
                }

                // Cálculo de minutos extra contra horario oficial
                if ($attendance->check_in && $attendance->check_out && $schedule?->shift) {
                    try {
                        $shiftStartStr = Carbon::parse($schedule->shift->start_time)->format('H:i:s');
                        $shiftEndStr = Carbon::parse($schedule->shift->end_time)->format('H:i:s');
                        
                        $expectedStart = Carbon::parse("{$dateStr} {$shiftStartStr}");
                        $expectedEnd = Carbon::parse("{$dateStr} {$shiftEndStr}");
                        if ($expectedEnd->lt($expectedStart)) $expectedEnd->addDay();
                        
                        $workedStart = Carbon::parse("{$dateStr} {$attendance->check_in}");
                        $workedEnd = Carbon::parse("{$dateStr} {$attendance->check_out}");
                        if ($workedEnd->lt($workedStart)) $workedEnd->addDay();

                        $workedMinutesCalc = $workedStart->diffInMinutes($workedEnd);
                        $expectedMinutes = $expectedStart->diffInMinutes($expectedEnd);

                        if ($workedMinutesCalc > $expectedMinutes) {
                            $dayExtraMins += ($workedMinutesCalc - $expectedMinutes);
                        }
                    } catch (\Exception $e) {}
                }

                $dayExtraMins += ($attendance->extra_hours ?? 0) * 60;

                if ($attendance->is_late && !$attendance->late_ignored) $counters['lates']++;

                switch ($attendance->incident_type) {
                    case IncidentType::ASISTENCIA:
                        $isPayable = true;
                        $dayIsAttendance = true;
                        $counters['days_worked'] += $shiftsCount;
                        break;
                    case IncidentType::VACACIONES:
                        $isPayable = true;
                        $counters['vacations']++;
                        $status = 'Vacaciones';
                        $dayCategory = 'vacation';
                        break;
                    case IncidentType::DIA_FESTIVO: 
                        $isPayable = true;
                        $counters['holidays_rest']++;
                        $status = 'Día Festivo';
                        $dayCategory = 'holiday_rest';
                        break;
                    case IncidentType::INCAPACIDAD_TRABAJO:
                    case IncidentType::INCAPACIDAD_GENERAL:
                        $counters['incapacity']++;
                        $status = 'Incapacidad';
                        $dayCategory = 'incapacity';
                        break;
                    case IncidentType::PERMISO_CON_GOCE:
                        $isPayable = true;
                        $counters['permissions']++;
                        $status = 'Permiso con Goce';
                        $dayCategory = 'permission';
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
                // --- Lógica si NO ASISTIÓ ---
                if ($holiday) {
                    // Verificar si tenía turno programado
                    if ($schedule && $schedule->shift_id) {
                        $isPayable = true;
                        $status = "Feriado ({$holiday->name})";
                    } else {
                        $isPayable = false; 
                        $status = "Feriado ({$holiday->name}) - Descanso";
                    }
                    
                    $dayCategory = 'holiday_rest';
                    $counters['holidays_rest']++;
                } else {
                    $status = 'Sin Registro';
                    if ($schedule && $schedule->shift_id) {
                        $dayIsAbsent = true;
                    }
                }
            }

            // --- COMISIONES ---
            $finalCommission = 0;
            $baseCommission = 0;
            $salesRef = 0;

            if ($attendance && !is_null($attendance->commission_amount)) {
                $baseCommission = $attendance->commission_amount;
                $finalCommission = $baseCommission * $shiftsCount;
                if ($operation) $salesRef = $operation->sales->sum('total');
            } elseif ($dayIsAttendance && $operation && $operation->is_closed) {
                $salesRef = $operation->sales->sum('total');
                if ($salesRef > 0) {
                    $refProduct = Product::find(1);
                    if ($refProduct && $refProduct->price > 0) {
                        $baseCommission = floor(($salesRef / ($refProduct->price * 10)) / 10) * 10;
                        $finalCommission = $baseCommission * $shiftsCount;
                    }
                }
            }

            if ($finalCommission > 0) {
                $totalCommissions += $finalCommission;
                $counters['commissions_count']++;
                $moneyBreakdown['commissions'] += $finalCommission;
                $details['commissions'][] = [
                    'date' => $dateStr,
                    'amount' => $finalCommission,
                    'base_amount' => $baseCommission,
                    'is_double' => $shiftsCount > 1,
                    'sales_ref' => $salesRef
                ];
            }

            // --- CALCULO PAGO BASE DEL DÍA ---
            if ($dayCategory === 'incapacity') {
                $dayPay = $employee->base_salary * 0.60;
                $moneyBreakdown['salary_incapacity'] += $dayPay;
                $totalPay += $dayPay;
            } elseif ($isPayable) {
                $dayPay = $employee->base_salary * $shiftsCount;
            }

            // --- AJUSTES POR FERIADO TRABAJADO ---
            if ($holiday) {
                if ($attendance && $attendance->incident_type === IncidentType::ASISTENCIA) {
                    $multiplier = $holiday->pay_multiplier;
                    $dayPay = $employee->base_salary * $multiplier * $shiftsCount;

                    $counters['holidays_worked']++;
                    $counters['days_worked'] -= $shiftsCount;

                    $status = "Feriado Laborado ({$holiday->name})";
                    $dayCategory = 'holiday_worked';
                }
            }

            // Acumular Dinero Desglosado
            if ($dayCategory !== 'incapacity' && $dayPay > 0) {
                $totalPay += $dayPay;
                switch ($dayCategory) {
                    case 'holiday_worked': $moneyBreakdown['salary_holidays_worked'] += $dayPay; break;
                    case 'holiday_rest': $moneyBreakdown['salary_holidays_rest'] += $dayPay; break;
                    case 'vacation': $moneyBreakdown['salary_vacations'] += $dayPay; break;
                    case 'permission': $moneyBreakdown['salary_permissions'] += $dayPay; break;
                    case 'other': $moneyBreakdown['salary_other'] += $dayPay; break;
                    default: $moneyBreakdown['salary_normal'] += $dayPay; break;
                }
            }

            // --- ESTADÍSTICAS ---
            $periodStats['late_minutes'] += $dayLateMins;
            $periodStats['extra_minutes'] += $dayExtraMins;
            if ($dayIsAbsent) $periodStats['unjustified_absences']++;
            if ($dayIsAttendance) $periodStats['attendance_days']++;

            $dailyStats[$dateStr] = [
                'late_minutes' => $dayLateMins,
                'extra_minutes' => $dayExtraMins,
                'is_attendance' => $dayIsAttendance,
                'is_absent' => $dayIsAbsent,
                // AQUÍ ESTABA EL PROBLEMA: No se estaba enviando 'worked_minutes'
                'worked_minutes' => $dayWorkedMinutes, 
            ];

            if ($dayPay > 0 || $attendance || ($holiday && $dayCategory === 'holiday_rest')) {
                $details['days'][] = [
                    'date' => $dateStr,
                    'amount' => $dayPay,
                    'concept' => $status . ($shiftsCount > 1 ? ' (Doble Turno)' : ''),
                    'incident' => $attendance?->incident_type->label() ?? ($holiday ? 'Día Festivo' : 'N/A'),
                    'category' => $dayCategory,
                    'shifts_count' => $shiftsCount,
                    'late_ignored' => $attendance?->late_ignored ?? false,
                    'is_late' => $attendance?->is_late ?? false,
                    'extra_minutes' => $dayExtraMins 
                ];
            }

            $current->addDay();
        }

        // 4. Bonos
        $bonusResult = $this->bonusService->calculate($employee, $periodStats, $dailyStats);

        $totalPay += $bonusResult['total_amount'];
        $totalPay += $totalCommissions;
        $totalDaysWorked = $counters['days_worked'] + $counters['holidays_worked'];

        $details['bonuses'] = $bonusResult['details'];

        return [
            'employee' => $employee->only(['id', 'first_name', 'last_name', 'base_salary', 'user_id', 'profile_photo_url']),
            'total_pay' => round($totalPay, 2),
            'days_worked' => $totalDaysWorked,
            'total_bonuses' => $bonusResult['total_amount'],
            'total_commissions' => round($totalCommissions, 2),
            'totals_breakdown' => $moneyBreakdown,
            'breakdown' => array_merge($details, $counters)
        ];
    }
}