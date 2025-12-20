<?php

namespace App\Services;

use App\Enums\IncidentType;
use App\Models\Attendance;
use App\Models\DailyOperation;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use App\Services\BonusService;
use Illuminate\Support\Facades\Log;

class PayrollService
{
    protected BonusService $bonusService;

    public function __construct()
    {
        // Inyectamos manualmente si no se usa DI en el constructor del controller
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
            $attendance = $attendances->get($dateStr);
            $schedule = $schedules->get($dateStr);
            $holiday = $holidays->get($dateStr);
            $operation = $dailyOperations->get($dateStr);

            $dayPay = 0;
            $status = 'Normal';
            $dayCategory = 'normal';
            $isPayable = false;

            $dayLateMins = 0;
            $dayExtraMins = 0;
            $dayIsAbsent = false;
            $dayIsAttendance = false;

            $shiftsCount = 1;

            if ($attendance) {
                // Cálculo Doble Turno
                if ($attendance->check_in && $attendance->check_out) {
                    try {
                        $in = Carbon::parse($attendance->check_in);
                        $out = Carbon::parse($attendance->check_out);
                        if ($out->lessThan($in)) $out->addDay();

                        $hoursWorked = $in->diffInHours($out);

                        if ($hoursWorked > 9) {
                            $shiftsCount = 2;
                            $counters['double_shifts']++;
                        }
                    } catch (\Exception $e) {
                    }
                }

                // Retardos
               if ($attendance->check_in && $schedule?->shift) {
                    try {
                        // Extraemos SOLO la hora para evitar error "Double date specification" 
                        // si $schedule->shift->start_time ya trae fecha.
                        $shiftTimeStr = Carbon::parse($schedule->shift->start_time)->format('H:i:s');
                        $checkInTimeStr = Carbon::parse($attendance->check_in)->format('H:i:s');
                        
                        // Construimos fecha correcta con hora limpia
                        $shiftStart = Carbon::parse("{$dateStr} {$shiftTimeStr}");
                        $checkIn = Carbon::parse("{$dateStr} {$checkInTimeStr}");
                        if ($checkIn->gt($shiftStart)) {
                            $dayLateMins = $attendance->late_ignored ? 0 : $shiftStart->diffInMinutes($checkIn, true);
                        }
                    } catch (\Exception $e) {
                        // Log::info($e);
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
                $status = 'Sin Registro';
                if ($schedule && $schedule->shift_id) {
                    $dayIsAbsent = true;
                }
            }

            // --- COMISIÓN DIARIA ---
            if ($dayIsAttendance && $operation && $operation->is_closed) {
                $dailySales = $operation->sales->sum('total');
                if ($dailySales > 0) {
                    $baseCommission = floor(($dailySales / 320) / 10) * 10;
                    $finalCommission = $baseCommission * $shiftsCount;

                    if ($finalCommission > 0) {
                        $totalCommissions += $finalCommission;
                        $counters['commissions_count']++;
                        $moneyBreakdown['commissions'] += $finalCommission;

                        $details['commissions'][] = [
                            'date' => $dateStr,
                            'amount' => $finalCommission,
                            'base_amount' => $baseCommission,
                            'is_double' => $shiftsCount > 1,
                            'sales_ref' => $dailySales
                        ];
                    }
                }
            }

            // --- PAGO DEL DÍA ---
            if ($dayCategory === 'incapacity') {
                $dayPay = $employee->base_salary * 0.60;
                $moneyBreakdown['salary_incapacity'] += $dayPay;
                $totalPay += $dayPay;
            } elseif ($isPayable) {
                $dayPay = $employee->base_salary * $shiftsCount;
            }

            if ($holiday) {
                if ($attendance && $attendance->incident_type === IncidentType::ASISTENCIA) {
                    $multiplier = $holiday->pay_multiplier;
                    $dayPay = $employee->base_salary * $multiplier * $shiftsCount;

                    $counters['holidays_worked']++;
                    $counters['days_worked'] -= $shiftsCount;

                    $status = "Feriado Laborado ({$holiday->name})";
                    $dayCategory = 'holiday_worked';
                } elseif ($isPayable && ($attendance->incident_type !== IncidentType::ASISTENCIA) && $dayCategory !== 'incapacity') {
                    $status = "Feriado ({$holiday->name})";
                    $dayCategory = 'holiday_rest';
                    if ($attendance->incident_type !== IncidentType::DIA_FESTIVO) {
                        $counters['holidays_rest']++;
                    }
                }
            }

            // Acumular Dinero
            if ($dayCategory !== 'incapacity' && $dayPay > 0) {
                $totalPay += $dayPay;
                switch ($dayCategory) {
                    case 'holiday_worked':
                        $moneyBreakdown['salary_holidays_worked'] += $dayPay;
                        break;
                    case 'holiday_rest':
                        $moneyBreakdown['salary_holidays_rest'] += $dayPay;
                        break;
                    case 'vacation':
                        $moneyBreakdown['salary_vacations'] += $dayPay;
                        break;
                    case 'permission':
                        $moneyBreakdown['salary_permissions'] += $dayPay;
                        break;
                    case 'other':
                        $moneyBreakdown['salary_other'] += $dayPay;
                        break;
                    default:
                        $moneyBreakdown['salary_normal'] += $dayPay;
                        break;
                }
            }

            // --- ACTUALIZAR ESTADÍSTICAS ---
            $periodStats['late_minutes'] += $dayLateMins;
            $periodStats['extra_minutes'] += $dayExtraMins;
            if ($dayIsAbsent) $periodStats['unjustified_absences']++;
            if ($dayIsAttendance) $periodStats['attendance_days']++;

            // [FIX] Llenar dailyStats para que el BonusService pueda evaluar reglas diarias
            $dailyStats[$dateStr] = [
                'late_minutes' => $dayLateMins,
                'extra_minutes' => $dayExtraMins,
                'is_attendance' => $dayIsAttendance,
                'is_absent' => $dayIsAbsent,
            ];

            if ($dayPay > 0 || $attendance) {
                $details['days'][] = [
                    'date' => $dateStr,
                    'amount' => $dayPay,
                    'concept' => $status . ($shiftsCount > 1 ? ' (Doble Turno)' : ''),
                    'incident' => $attendance?->incident_type->label() ?? 'N/A',
                    'category' => $dayCategory,
                    'shifts_count' => $shiftsCount,
                    'late_ignored' => $attendance?->late_ignored ?? false,
                    'is_late' => $attendance?->is_late ?? false,
                ];
            }

            $current->addDay();
        }

        // 4. Calcular Bonos (Delegado al BonusService)
        // Pasamos las estadísticas acumuladas y las diarias
        $bonusResult = $this->bonusService->calculate($employee, $periodStats, $dailyStats);

        $totalPay += $bonusResult['total_amount'];
        $totalPay += $totalCommissions;
        $totalDaysWorked = $counters['days_worked'] + $counters['holidays_worked'];

        // Fusionar los detalles de bonos en el array principal
        $details['bonuses'] = $bonusResult['details'];

        return [
            'employee' => $employee->only(['id', 'first_name', 'last_name', 'base_salary']),
            'total_pay' => round($totalPay, 2),
            'days_worked' => $totalDaysWorked,
            'total_bonuses' => $bonusResult['total_amount'], // Total retornado por el servicio
            'total_commissions' => round($totalCommissions, 2),
            'totals_breakdown' => $moneyBreakdown,
            'breakdown' => array_merge($details, $counters)
        ];
    }
}
