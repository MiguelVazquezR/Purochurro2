<?php

namespace App\Http\Controllers;

use App\Enums\IncidentType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Holiday;
use App\Models\PayrollReceipt;
use App\Models\WorkSchedule;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayrollController extends Controller
{
    public function index()
    {
        $currentStart = Carbon::now()->startOfWeek(Carbon::SUNDAY)->format('Y-m-d');
        $weekStarts = collect([$currentStart]);

        $receiptStarts = PayrollReceipt::select('start_date')
            ->distinct()
            ->pluck('start_date')
            ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'));
            
        $weekStarts = $weekStarts->merge($receiptStarts);

        $attendanceDates = Attendance::select('date')->distinct()->get()
            ->map(fn($a) => Carbon::parse($a->date)->startOfWeek(Carbon::SUNDAY)->format('Y-m-d'));
            
        $weekStarts = $weekStarts->merge($attendanceDates);

        $weeks = $weekStarts->unique()
            ->sortDesc()
            ->values()
            ->map(function ($dateStr) use ($currentStart) {
                $start = Carbon::parse($dateStr);
                $end = $start->copy()->endOfWeek(Carbon::SATURDAY);

                $isClosed = false;
                if (auth()->id() === 1) {
                    $isClosed = PayrollReceipt::where('start_date', $dateStr)->exists();
                } else {
                    $employee = Employee::where('user_id', auth()->id())->first();
                    if ($employee) {
                        $isClosed = PayrollReceipt::where('start_date', $dateStr)
                            ->where('employee_id', $employee->id)
                            ->exists();
                    }
                }

                return [
                    'label' => "Semana del {$start->format('d M')} al {$end->format('d M Y')}",
                    'start_date' => $start->format('Y-m-d'),
                    'end_date' => $end->format('Y-m-d'),
                    'is_current' => $dateStr === $currentStart,
                    'is_closed' => $isClosed,
                ];
            });

        return Inertia::render('Payroll/Index', [
            'weeks' => $weeks
        ]);
    }

    public function week(Request $request, string $startDate)
    {
        if (auth()->id() !== 1) {
            return $this->employeeWeekView($startDate);
        }

        $start = Carbon::parse($startDate)->startOfWeek(Carbon::SUNDAY);
        $end = $start->copy()->endOfWeek(Carbon::SATURDAY);

        $months = array_unique([$start->month, $end->month]);

        $holidaysCollection = Holiday::query()
            ->whereIn(DB::raw('MONTH(date)'), $months)
            ->get();

        $holidaysLookup = $holidaysCollection->keyBy(function ($h) {
            return Carbon::parse($h->date)->format('m-d');
        });

        $holidaysPayload = $holidaysCollection->map(function ($holiday) {
            return [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'date' => $holiday->date,
                'pay_multiplier' => $holiday->pay_multiplier,
                'mandatory' => $holiday->mandatory ?? true,
            ];
        });

        $employees = Employee::with(['media'])
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get();

        $payrollData = $employees->map(function ($employee) use ($start, $end, $holidaysLookup) {
            $days = [];
            $period = $start->copy();

            while ($period <= $end) {
                $dateStr = $period->format('Y-m-d');
                $monthDay = $period->format('m-d');

                $attendances = Attendance::where('employee_id', $employee->id)
                    ->where('date', $dateStr)
                    ->get();

                $attendance = $attendances->first();
                $dailyCommission = $attendances->sum('commission_amount');

                $schedule = WorkSchedule::with('shift')
                    ->where('employee_id', $employee->id)
                    ->where('date', $dateStr)
                    ->first();

                $incident = $attendance ? $attendance->incident_type : null;
                $holiday = $holidaysLookup->get($monthDay);
                
                // --- Cálculo de Turnos y Minutos para la vista ---
                $shiftsCount = 1;
                $workedMinutes = 0;
                
                if ($attendance && $attendance->check_in && $attendance->check_out) {
                    try {
                        $in = Carbon::parse($attendance->check_in);
                        $out = Carbon::parse($attendance->check_out);
                        if ($out->lessThan($in)) $out->addDay();
                        $workedMinutes = $in->diffInMinutes($out);
                        
                        // Regla visual: >= 540 minutos (9 horas)
                        if ($workedMinutes >= 540) {
                            $shiftsCount = 2;
                        }
                    } catch (\Exception $e) {}
                }

                if (!$attendance) {
                    if ($holiday) {
                        $incidentLabel = $holiday->name; 
                        $incident = IncidentType::DIA_FESTIVO->value;
                    } elseif ($schedule && $schedule->shift_id) {
                        $incidentLabel = 'Falta / Pendiente';
                    } else {
                        $incidentLabel = 'Descanso';
                    }
                } else {
                    $incidentLabel = $attendance->incident_type->label();
                }

                $days[] = [
                    'date' => $dateStr,
                    'day_name' => $period->locale('es')->dayName,
                    'attendance_id' => $attendance?->id,
                    'incident_type' => $incident, 
                    'incident_label' => $incidentLabel,
                    'check_in' => $attendance?->check_in ? Carbon::parse($attendance->check_in)->format('H:i') : null,
                    'check_out' => $attendance?->check_out ? Carbon::parse($attendance->check_out)->format('H:i') : null,
                    'check_in_photo' => $attendance?->getFirstMediaUrl('check_in_photo'),
                    'check_out_photo' => $attendance?->getFirstMediaUrl('check_out_photo'),
                    'is_late' => $attendance?->is_late ?? false,
                    'late_ignored' => $attendance?->late_ignored ?? false,
                    'admin_notes' => $attendance?->admin_notes,
                    'schedule_shift' => $schedule?->shift?->name,
                    'is_rest_day' => (!$schedule || !$schedule->shift_id),
                    'commission' => $dailyCommission > 0 ? $dailyCommission : null,
                    'holiday_data' => $holiday ? [
                        'name' => $holiday->name,
                        'multiplier' => $holiday->pay_multiplier ?? 2.0
                    ] : null,
                    // Nuevos campos para visualización de turnos
                    'shifts_count' => $shiftsCount,
                    'worked_minutes' => $workedMinutes,
                ];

                $period->addDay();
            }

            return [
                'employee' => $employee,
                'days' => $days,
            ];
        });

        return Inertia::render('Payroll/Show', [
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
            'payrollData' => $payrollData,
            'holidays' => $holidaysPayload,
            'incidentTypes' => array_column(IncidentType::cases(), 'value'),
        ]);
    }

    private function employeeWeekView(string $startDate)
    {
        $user = auth()->user();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $start = Carbon::parse($startDate)->startOfWeek(Carbon::SUNDAY);
        $end = $start->copy()->endOfWeek(Carbon::SATURDAY);

        $receipt = PayrollReceipt::where('employee_id', $employee->id)
            ->where('start_date', $start->format('Y-m-d'))
            ->first();

        if ($receipt) {
            $payrollData = [
                'total_pay' => $receipt->total_pay,
                'days_worked' => $receipt->days_worked,
                'total_bonuses' => $receipt->total_bonuses,
                'breakdown' => $receipt->breakdown_data,
                'paid_at' => $receipt->paid_at,
                'is_closed' => true
            ];
        } else {
            $service = new PayrollService();
            $calc = $service->calculate($employee, $start, $end);

            $payrollData = [
                'total_pay' => $calc['total_pay'],
                'days_worked' => $calc['days_worked'],
                'total_bonuses' => $calc['total_bonuses'],
                'breakdown' => array_merge($calc['breakdown'], [
                    'totals_breakdown' => $calc['totals_breakdown'],
                    'commissions_total' => $calc['total_commissions'] ?? 0
                ]),
                'paid_at' => null,
                'is_closed' => false
            ];
        }

        $months = array_unique([$start->month, $end->month]);
        $holidaysLookup = Holiday::whereIn(DB::raw('MONTH(date)'), $months)
            ->get()
            ->keyBy(fn($h) => Carbon::parse($h->date)->format('m-d'));

        $days = [];
        $period = $start->copy();

        while ($period <= $end) {
            $dateStr = $period->format('Y-m-d');
            $monthDay = $period->format('m-d');

            $attendance = Attendance::where('employee_id', $employee->id)
                ->where('date', $dateStr)
                ->first();

            $schedule = WorkSchedule::with('shift')
                ->where('employee_id', $employee->id)
                ->where('date', $dateStr)
                ->first();

            $holiday = $holidaysLookup->get($monthDay);

            $incidentLabel = 'Asistencia';
            if ($attendance) {
                $incidentLabel = $attendance->incident_type->label();
            } else {
                if ($holiday) {
                    $incidentLabel = $holiday->name; 
                } elseif ($schedule && $schedule->shift_id) {
                    $incidentLabel = Carbon::parse($dateStr)->isFuture() ? 'Programado' : 'Falta / Pendiente';
                } else {
                    $incidentLabel = 'Descanso';
                }
            }

            $days[] = [
                'date' => $dateStr,
                'day_name' => $period->locale('es')->dayName,
                'incident_type' => $attendance?->incident_type?->value ?? ($holiday ? IncidentType::DIA_FESTIVO->value : null),
                'incident_label' => $incidentLabel,
                'check_in' => $attendance?->check_in ? Carbon::parse($attendance->check_in)->format('H:i') : null,
                'check_out' => $attendance?->check_out ? Carbon::parse($attendance->check_out)->format('H:i') : null,
                'is_late' => $attendance?->is_late ?? false,
                'schedule_shift' => $schedule?->shift?->name,
                'shift_color' => $schedule?->shift?->color,
                'is_rest_day' => (!$schedule || !$schedule->shift_id),
                'holiday_data' => $holiday ? [
                    'name' => $holiday->name,
                    'multiplier' => $holiday->pay_multiplier ?? 2.0
                ] : null,
            ];

            $period->addDay();
        }

        return Inertia::render('Payroll/EmployeeWeek', [
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
            'days' => $days,
            'payrollData' => $payrollData,
            'employee' => [
                'full_name' => $employee->full_name,
                'base_salary' => $employee->base_salary,
                'vacation_balance' => $employee->vacation_balance
            ],
        ]);
    }

    public function receipts(Request $request, string $startDate)
    {
        $start = Carbon::parse($startDate)->startOfWeek(Carbon::SUNDAY);
        $end = $start->copy()->endOfWeek(Carbon::SATURDAY);

        $employees = Employee::with(['recurringBonuses', 'bonuses'])
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get();

        $payrollService = new PayrollService();
        $receiptsData = [];

        foreach ($employees as $employee) {
            $calculation = $payrollService->calculate($employee, $start, $end);
            $calculation['commissions_detail'] = $calculation['breakdown']['commissions'] ?? [];
            $receiptsData[] = $calculation;
        }

        return Inertia::render('Payroll/Receipts', [
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
            'payrollData' => $receiptsData,
        ]);
    }

    public function updateDay(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'incident_type' => ['required', Rule::enum(IncidentType::class)],
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'late_ignored' => 'boolean',
            'admin_notes' => 'nullable|string',
            'commission_amount' => 'nullable|numeric|min:0' 
        ]);

        DB::beginTransaction();
        try {
            $attendance = Attendance::where('employee_id', $validated['employee_id'])
                ->whereDate('date', $validated['date'])
                ->first();

            $oldIncident = $attendance ? $attendance->incident_type : null;
            $newIncident = IncidentType::from($validated['incident_type']);

            if (!$attendance) {
                $attendance = new Attendance();
                $attendance->employee_id = $validated['employee_id'];
                $attendance->date = $validated['date'];
            }

            // --- Lógica de Retardos ---
            $isLate = false;
            if (!empty($validated['check_in'])) {
                $schedule = WorkSchedule::with('shift')
                    ->where('employee_id', $validated['employee_id'])
                    ->whereDate('date', $validated['date'])
                    ->first();

                if ($schedule && $schedule->shift) {
                    $checkInTime = Carbon::parse($validated['date'] . ' ' . $validated['check_in']);
                    $shiftTimeOnly = Carbon::parse($schedule->shift->start_time)->format('H:i:s');
                    $entryLimit = Carbon::parse($validated['date'] . ' ' . $shiftTimeOnly);
                    if ($checkInTime->greaterThan($entryLimit)) {
                        $isLate = true;
                    }
                }
            }

            $attendance->incident_type = $newIncident;
            $attendance->check_in = $validated['check_in'] ?? null;
            $attendance->check_out = $validated['check_out'] ?? null;
            $attendance->is_late = $isLate;
            $attendance->late_ignored = $validated['late_ignored'] ?? false;
            $attendance->admin_notes = $validated['admin_notes'] ?? null;
            $attendance->commission_amount = $validated['commission_amount'] ?? 0;

            $attendance->save();

            // Ajuste de saldos de vacaciones
            $employee = Employee::find($validated['employee_id']);
            $dateStr = Carbon::parse($validated['date'])->format('d/m/Y');

            if ($newIncident === IncidentType::VACACIONES && $oldIncident !== IncidentType::VACACIONES) {
                $employee->adjustVacationBalance(
                    -1,
                    'usage',
                    "Día tomado: $dateStr. Nota: " . ($validated['admin_notes'] ?? ''),
                    auth()->id()
                );
            } elseif ($oldIncident === IncidentType::VACACIONES && $newIncident !== IncidentType::VACACIONES) {
                $employee->adjustVacationBalance(
                    1,
                    'adjustment',
                    "Devolución día: $dateStr (Cambio de incidencia). Nota: " . ($validated['admin_notes'] ?? ''),
                    auth()->id()
                );
            }

            DB::commit();
            return back()->with('success', 'Registro actualizado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    public function deleteDay($id)
    {
        if (auth()->id() !== 1) abort(403);

        $attendance = Attendance::findOrFail($id);

        if ($attendance->incident_type === IncidentType::VACACIONES) {
            $employee = Employee::find($attendance->employee_id);
            $dateStr = Carbon::parse($attendance->date)->format('d/m/Y');
            $employee->adjustVacationBalance(
                1,
                'adjustment',
                "Devolución día: $dateStr (Registro eliminado).",
                auth()->id()
            );
        }

        $attendance->delete();

        return back()->with('success', 'Registro de asistencia eliminado.');
    }

    public function settlement(Request $request, string $startDate)
    {
        if (auth()->id() !== 1) abort(403);

        $start = Carbon::parse($startDate)->startOfWeek(Carbon::SUNDAY);
        $end = $start->copy()->endOfWeek(Carbon::SATURDAY);

        $isClosed = PayrollReceipt::where('start_date', $start->format('Y-m-d'))->exists();

        $employees = Employee::with(['recurringBonuses'])
            ->where('is_active', true)
            ->get();

        $payrollService = new PayrollService();
        $settlements = [];
        $grandTotal = 0;

        foreach ($employees as $employee) {
            $calculation = $payrollService->calculate($employee, $start, $end);
            $settlements[] = $calculation;
            $grandTotal += $calculation['total_pay'];
        }

        return Inertia::render('Payroll/Settlement', [
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
            'settlements' => $settlements,
            'grandTotal' => round($grandTotal, 2),
            'isClosed' => $isClosed
        ]);
    }

    public function storeSettlement(Request $request)
    {
        if (auth()->id() !== 1) abort(403);

        $request->validate([
            'start_date' => 'required|date',
            'mark_as_paid' => 'boolean'
        ]);

        $start = Carbon::parse($request->start_date)->startOfWeek(Carbon::SUNDAY);
        $end = $start->copy()->endOfWeek(Carbon::SATURDAY);

        if (PayrollReceipt::where('start_date', $start->format('Y-m-d'))->exists()) {
            return back()->with('error', 'Esta nómina ya fue cerrada anteriormente.');
        }

        $employees = Employee::with(['recurringBonuses'])->where('is_active', true)->get();
        $payrollService = new PayrollService();
        
        DB::beginTransaction();
        try {
            $receiptsCount = 0;
            $totalPayrollAmount = 0; 

            foreach ($employees as $employee) {
                $calc = $payrollService->calculate($employee, $start, $end);

                $finalBreakdownData = $calc['breakdown'];
                $finalBreakdownData['totals_breakdown'] = $calc['totals_breakdown'];
                $finalBreakdownData['commissions_total'] = $calc['total_commissions'];
                $finalBreakdownData['commissions_detail'] = $calc['breakdown']['commissions'] ?? [];

                PayrollReceipt::create([
                    'employee_id' => $employee->id,
                    'start_date' => $start->format('Y-m-d'),
                    'end_date' => $end->format('Y-m-d'),
                    'base_salary_snapshot' => $employee->base_salary,
                    'total_pay' => $calc['total_pay'],
                    'days_worked' => $calc['days_worked'],
                    'total_bonuses' => $calc['total_bonuses'],
                    'breakdown_data' => $finalBreakdownData,
                    'paid_at' => $request->mark_as_paid ? now() : null,
                ]);

                $totalPayrollAmount += $calc['total_pay'];
                $receiptsCount++;
            }

            $attendances = Attendance::whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])->get();
            foreach ($attendances as $attendance) {
                $attendance->clearMediaCollection('check_in_photo');
                $attendance->clearMediaCollection('check_out_photo');
            }

            if ($request->mark_as_paid && $totalPayrollAmount > 0) {
                Expense::create([
                    'concept' => 'Nómina Semanal ' . $start->format('d/m') . ' - ' . $end->format('d/m/Y'),
                    'amount' => $totalPayrollAmount,
                    'date' => now(), 
                    'notes' => "Cierre de nómina. {$receiptsCount} recibos generados.",
                    'user_id' => auth()->id(), 
                ]);
            }

            DB::commit();

            return redirect()->route('payroll.index')
                ->with('success', "Nómina cerrada. {$receiptsCount} recibos generados.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al cerrar la nómina: ' . $e->getMessage());
        }
    }
}