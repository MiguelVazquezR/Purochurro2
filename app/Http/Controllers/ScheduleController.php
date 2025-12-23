<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    /**
     * Muestra el calendario de horarios.
     */
    public function index(Request $request)
    {
        // 1. Definir rango de fechas (Semana Domingo - Sábado)
        // Usamos Carbon::SUNDAY y Carbon::SATURDAY explícitamente para forzar este comportamiento
        // independientemente de la configuración regional del servidor.
        $startDate = $request->input('start_date') 
            ? Carbon::parse($request->input('start_date'))->startOfWeek(Carbon::SUNDAY) 
            : Carbon::now()->startOfWeek(Carbon::SUNDAY);

        $endDate = $startDate->copy()->endOfWeek(Carbon::SATURDAY);

        // 2. Cargar Catálogo de Turnos (para el selector en la celda)
        $shifts = Shift::where('is_active', true)->get();

        // 3. Cargar Empleados + Horarios (OPTIMIZADO)
        $employees = Employee::with([
                'media', 
                'user',
                // Cargamos solo los horarios dentro del rango de fechas de esta semana
                'workSchedules' => function($query) use ($startDate, $endDate) {
                    $query->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                          ->with('shift');
                }
            ])
            ->where('is_active', true)
            ->orderBy('first_name') // Ordenamos alfabéticamente por nombre
            ->get()
            ->map(function ($employee) {
                // 4. Transformar para el Frontend
                $employee->week_schedules = $employee->workSchedules->keyBy(fn($schedule) => $schedule->date->format('Y-m-d'));
                return $employee;
            });

        return Inertia::render('Schedule/Index', [
            'employees' => $employees,
            'shifts' => $shifts,
            'weekStart' => $startDate->format('Y-m-d'),
            'weekEnd' => $endDate->format('Y-m-d'),
        ]);
    }

    /**
     * Guarda o actualiza un horario específico de un día (Celda individual).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'shift_id' => 'nullable|exists:shifts,id', // Null = Descanso
            'date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        WorkSchedule::updateOrCreate(
            [
                'employee_id' => $validated['employee_id'], 
                'date' => $validated['date']
            ],
            [
                'shift_id' => $validated['shift_id'],
                'notes' => $request->notes ?? null,
                'is_published' => true, 
            ]
        );

        return back()->with('success', 'Horario actualizado correctamente.');
    }

    /**
     * Generación Masiva: Aplica la plantilla predeterminada a la semana actual.
     */
    public function generateWeek(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
        ]);

        // Aseguramos que la generación también comience en Domingo
        $startDate = Carbon::parse($request->start_date)->startOfWeek(Carbon::SUNDAY);
        
        DB::transaction(function () use ($startDate) {
            $employees = Employee::where('is_active', true)->get();

            foreach ($employees as $employee) {
                $template = $employee->default_schedule_template;

                // Si no tiene plantilla configurada, saltamos
                if (empty($template)) continue;

                // Iteramos los 7 días de la semana
                for ($i = 0; $i < 7; $i++) {
                    $currentDate = $startDate->copy()->addDays($i);
                    // 'l' devuelve el nombre completo en inglés: Sunday, Monday...
                    $dayName = strtolower($currentDate->format('l')); 

                    // Obtenemos el ID del turno configurado para ese día
                    $shiftId = $template[$dayName] ?? null;

                    // Creamos o actualizamos el horario
                    WorkSchedule::updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'date' => $currentDate->format('Y-m-d')
                        ],
                        [
                            'shift_id' => $shiftId,
                            'is_published' => true
                        ]
                    );
                }
            }
        });

        return back()->with('success', 'Semana generada basada en las plantillas de empleados.');
    }
}