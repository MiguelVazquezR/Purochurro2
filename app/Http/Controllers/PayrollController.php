<?php

namespace App\Http\Controllers;

use App\Enums\IncidentType;
use App\Models\Attendance;
use App\Models\Employee;
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
        $weeks = [];
        $currentStart = Carbon::now()->startOfWeek(Carbon::SUNDAY);

        for ($i = 0; $i < 12; $i++) {
            $start = $currentStart->copy()->subWeeks($i);
            $end = $start->copy()->endOfWeek(Carbon::SATURDAY);
            $isClosed = PayrollReceipt::where('start_date', $start->format('Y-m-d'))->exists();

            $weeks[] = [
                'label' => "Semana del {$start->format('d M')} al {$end->format('d M Y')}",
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'is_current' => $i === 0,
                'is_closed' => $isClosed,
            ];
        }

        return Inertia::render('Payroll/Index', [
            'weeks' => $weeks
        ]);
    }

    public function week(Request $request, string $startDate)
    {
        $start = Carbon::parse($startDate)->startOfWeek(Carbon::SUNDAY);
        $end = $start->copy()->endOfWeek(Carbon::SATURDAY);

        // --- Lógica de Festivos Recurrentes (Día/Mes) ---
        $months = array_unique([$start->month, $end->month]);

        // Buscamos festivos que coincidan en MES, ignorando el año
        $holidaysCollection = Holiday::query()
            ->whereIn(DB::raw('MONTH(date)'), $months)
            ->get();

        // Mapa para búsqueda rápida (Clave: 'MM-DD')
        $holidaysLookup = $holidaysCollection->keyBy(function ($h) {
            return Carbon::parse($h->date)->format('m-d');
        });

        // Payload para Vue (necesario para tu Show.vue)
        $holidaysPayload = $holidaysCollection->map(function ($holiday) {
            return [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'date' => $holiday->date, // Vue usará esto para matching visual
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
                
                $attendance = Attendance::where('employee_id', $employee->id)
                    ->where('date', $dateStr)
                    ->first();

                $schedule = WorkSchedule::with('shift')
                    ->where('employee_id', $employee->id)
                    ->where('date', $dateStr)
                    ->first();

                $incident = $attendance ? $attendance->incident_type : null;
                
                // Buscamos festivo por día/mes
                $holiday = $holidaysLookup->get($monthDay); 
                
                if (!$attendance) {
                    if ($schedule && $schedule->shift_id) {
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
                    'incident_type' => $incident?->value,
                    'incident_label' => $incidentLabel,
                    // Mantenemos formato 24h (H:i) para enviar al front. 
                    // El front se encargará de mostrarlo como 12h.
                    'check_in' => $attendance?->check_in ? Carbon::parse($attendance->check_in)->format('H:i') : null,
                    'check_out' => $attendance?->check_out ? Carbon::parse($attendance->check_out)->format('H:i') : null,
                    'is_late' => $attendance?->is_late ?? false,
                    'late_ignored' => $attendance?->late_ignored ?? false,
                    'admin_notes' => $attendance?->admin_notes, 
                    'schedule_shift' => $schedule?->shift?->name,
                    'is_rest_day' => (!$schedule || !$schedule->shift_id),
                    'holiday_data' => $holiday ? [ 
                        'name' => $holiday->name,
                        'multiplier' => $holiday->pay_multiplier ?? 2.0 
                    ] : null,
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

    public function updateDay(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'incident_type' => ['required', Rule::enum(IncidentType::class)],
            'check_in' => 'nullable|date_format:H:i', // Espera formato 24h
            'check_out' => 'nullable|date_format:H:i', // Espera formato 24h
            'late_ignored' => 'boolean',
            'admin_notes' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            // Buscar asistencia existente
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

            // Llenar datos (incluyendo admin_notes)
            $attendance->fill([
                'incident_type' => $newIncident,
                'check_in' => $validated['check_in'] ?? null,
                'check_out' => $validated['check_out'] ?? null,
                'late_ignored' => $validated['late_ignored'] ?? false,
                'admin_notes' => $validated['admin_notes'] ?? null,
            ]);

            $attendance->save();

            // --- Lógica de Vacaciones ---
            $employee = Employee::find($validated['employee_id']);
            $dateStr = Carbon::parse($validated['date'])->format('d/m/Y');

            // Caso 1: Se asignan vacaciones (y antes NO era vacaciones) -> RESTAR
            if ($newIncident === IncidentType::VACACIONES && $oldIncident !== IncidentType::VACACIONES) {
                $employee->adjustVacationBalance(
                    -1, 
                    'usage', 
                    "Día tomado: $dateStr. Nota: " . ($validated['admin_notes'] ?? ''),
                    auth()->id()
                );
            }
            // Caso 2: Se quitan vacaciones (y antes SI era vacaciones) -> REEMBOLSAR
            elseif ($oldIncident === IncidentType::VACACIONES && $newIncident !== IncidentType::VACACIONES) {
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

    public function settlement(Request $request, string $startDate)
    {
        $start = Carbon::parse($startDate)->startOfWeek(Carbon::SUNDAY);
        $end = $start->copy()->endOfWeek(Carbon::SATURDAY);

        $isClosed = PayrollReceipt::where('start_date', $start->format('Y-m-d'))->exists();

        $employees = Employee::with('bonuses')
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
        $request->validate([
            'start_date' => 'required|date',
            'mark_as_paid' => 'boolean'
        ]);

        $start = Carbon::parse($request->start_date)->startOfWeek(Carbon::SUNDAY);
        $end = $start->copy()->endOfWeek(Carbon::SATURDAY);

        if (PayrollReceipt::where('start_date', $start->format('Y-m-d'))->exists()) {
            return back()->with('error', 'Esta nómina ya fue cerrada anteriormente.');
        }

        $employees = Employee::with('bonuses')->where('is_active', true)->get();
        $payrollService = new PayrollService();
        $weeklyVacationAccrual = 6 / 52; 

        DB::beginTransaction();
        try {
            $receiptsCount = 0;

            foreach ($employees as $employee) {
                $calc = $payrollService->calculate($employee, $start, $end);

                PayrollReceipt::create([
                    'employee_id' => $employee->id,
                    'start_date' => $start->format('Y-m-d'),
                    'end_date' => $end->format('Y-m-d'),
                    'base_salary_snapshot' => $employee->base_salary,
                    'total_pay' => $calc['total_pay'],
                    'days_worked' => $calc['days_worked'],
                    'total_bonuses' => $calc['total_bonuses'],
                    'breakdown_data' => $calc['breakdown'], 
                    'paid_at' => $request->mark_as_paid ? now() : null,
                ]);
                
                // Acumulación semanal de vacaciones
                $employee->adjustVacationBalance(
                    $weeklyVacationAccrual, 'accrual', "Acumulación semanal (Cierre Nómina)", auth()->id()
                );

                $receiptsCount++;
            }

            // LIMPIEZA DE FOTOS
            $attendances = Attendance::whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])->get();

            foreach ($attendances as $attendance) {
                $attendance->clearMediaCollection('check_in_photo');
                $attendance->clearMediaCollection('check_out_photo');
            }

            DB::commit();
            
            return redirect()->route('payroll.index')
                ->with('success', "Nómina cerrada. {$receiptsCount} recibos generados. Fotos de evidencia eliminadas.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al cerrar la nómina: ' . $e->getMessage());
        }
    }
}