<?php

namespace App\Http\Controllers;

use App\Enums\IncidentType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\IncidentRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IncidentRequestController extends Controller
{
    /**
     * Muestra las solicitudes.
     */
    public function index()
    {
        $user = auth()->user();
        
        $employeeProfile = Employee::where('user_id', $user->id)->first();

        $query = IncidentRequest::with('employee');
        $employeeStats = null;

        if ($employeeProfile) {
            $query->where('employee_id', $employeeProfile->id);

            // --- CÁLCULO DE META ANUAL PARA VALIDACIÓN FRONTEND ---
            // Contamos los días laborales en su horario base
            $schedule = $employeeProfile->default_schedule_template;
            $entitledDays = 0;
            if (is_array($schedule)) {
                $entitledDays = count(array_filter($schedule, fn($val) => !is_null($val)));
            }

            $employeeStats = [
                'balance' => $employeeProfile->vacation_balance,
                'entitled_days' => $entitledDays
            ];

        } else {
            $query->orderByRaw("CASE WHEN status = 'pending' THEN 1 ELSE 2 END");
        }

        return Inertia::render('IncidentRequest/Index', [
            'requests' => $query->latest()->paginate(10),
            'canApprove' => is_null($employeeProfile),
            'incidentTypes' => array_column(IncidentType::cases(), 'value'),
            'employeeStats' => $employeeStats, // Enviamos datos para validar en vista
        ]);
    }

    /**
     * Empleado crea una solicitud.
     */
    public function store(Request $request)
    {
        $employee = Employee::where('user_id', auth()->id())->firstOrFail();

        $validated = $request->validate([
            'incident_type' => ['required', Rule::enum(IncidentType::class)],
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'employee_reason' => 'required|string|max:500',
        ]);

        // --- VALIDACIÓN DE NEGOCIO: REQUISITO DE VACACIONES ---
        if ($validated['incident_type'] === IncidentType::VACACIONES->value) {
            // 1. Calcular meta anual
            $schedule = $employee->default_schedule_template;
            $entitledDays = 0;
            if (is_array($schedule)) {
                $entitledDays = count(array_filter($schedule, fn($val) => !is_null($val)));
            }

            // 2. Verificar si tiene acumulado el total anual
            if ($employee->vacation_balance < $entitledDays) {
                return back()->withErrors([
                    'incident_type' => "No cumples con el requisito. Necesitas tener acumulados tus {$entitledDays} días anuales completos para solicitar vacaciones. (Saldo actual: {$employee->vacation_balance})"
                ]);
            }
        }

        IncidentRequest::create([
            'employee_id' => $employee->id,
            'incident_type' => $validated['incident_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'employee_reason' => $validated['employee_reason'],
            'status' => 'pending'
        ]);

        return back()->with('success', 'Solicitud enviada correctamente.');
    }

    /**
     * Admin aprueba o rechaza.
     */
    public function updateStatus(Request $request, IncidentRequest $incidentRequest)
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_response' => 'nullable|string|required_if:status,rejected',
        ]);

        if ($incidentRequest->status !== 'pending') {
            return back()->with('error', 'Esta solicitud ya fue procesada.');
        }

        DB::beginTransaction();
        try {
            $incidentRequest->update([
                'status' => $validated['status'],
                'admin_response' => $validated['admin_response'] ?? null, 
                'processed_by' => auth()->id(),
                'processed_at' => now(),
            ]);

            // SI SE APRUEBA: Generar registros en Attendance
            if ($validated['status'] === 'approved') {
                $start = Carbon::parse($incidentRequest->start_date);
                $end = Carbon::parse($incidentRequest->end_date);
                $daysCount = 0;

                $current = $start->copy();
                while ($current <= $end) {
                    Attendance::updateOrCreate(
                        [
                            'employee_id' => $incidentRequest->employee_id,
                            'date' => $current->format('Y-m-d')
                        ],
                        [
                            'incident_type' => $incidentRequest->incident_type,
                            'check_in' => null,
                            'check_out' => null,
                            'admin_notes' => "Solicitud Folio #{$incidentRequest->id} Aprobada: " . $incidentRequest->employee_reason
                        ]
                    );
                    
                    $daysCount++;
                    $current->addDay();
                }

                // SI SON VACACIONES: Descontar saldo
                if ($incidentRequest->incident_type === IncidentType::VACACIONES) {
                    $employee = $incidentRequest->employee;
                    $employee->adjustVacationBalance(
                        -$daysCount,
                        'usage',
                        "Solicitud Aprobada #{$incidentRequest->id} ({$start->format('d/m')} al {$end->format('d/m')})",
                        auth()->id()
                    );
                }
            }

            DB::commit();
            return back()->with('success', 'Solicitud procesada correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al procesar: ' . $e->getMessage());
        }
    }
}