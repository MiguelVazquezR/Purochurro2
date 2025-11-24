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
     * Recibe opcionalmente 'start_date' para navegar entre semanas.
     */
    public function index(Request $request)
    {
        // Por defecto, iniciamos el lunes de la semana actual
        $startDate = $request->input('start_date') 
            ? Carbon::parse($request->input('start_date'))->startOfWeek() 
            : Carbon::now()->startOfWeek();

        $endDate = $startDate->copy()->endOfWeek();

        // Obtenemos todos los turnos activos para el dropdown
        $shifts = Shift::where('is_active', true)->get();

        // Cargamos empleados con sus horarios SOLO de esta semana
        $employees = Employee::with(['media', 'user'])
            ->where('is_active', true)
            ->get()
            ->map(function ($employee) use ($startDate, $endDate) {
                // Buscamos los horarios ya creados en DB para este rango
                $schedules = WorkSchedule::where('employee_id', $employee->id)
                    ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->get()
                    ->keyBy(fn($item) => $item->date->format('Y-m-d'));

                $employee->week_schedules = $schedules;
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
     * Guarda o actualiza un horario específico de un día.
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
                'notes' => $request->notes,
                'is_published' => true, // O false si manejas borradores
            ]
        );

        return back()->with('success', 'Horario actualizado.');
    }

    /**
     * Generación Masiva: Aplica el 'default_schedule_template' a la semana seleccionada
     * para todos los empleados (o uno específico).
     */
    public function generateWeek(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfWeek();
        
        DB::transaction(function () use ($startDate) {
            $employees = Employee::where('is_active', true)->get();

            foreach ($employees as $employee) {
                $template = $employee->default_schedule_template;

                // Si no tiene plantilla, saltamos (o asignamos descanso por defecto)
                if (!$template) continue;

                // Iteramos 7 días (0 = Lunes, 6 = Domingo en Carbon startOfWeek)
                for ($i = 0; $i < 7; $i++) {
                    $currentDate = $startDate->copy()->addDays($i);
                    // Obtenemos el nombre del día en minúsculas (monday, tuesday...)
                    $dayName = strtolower($currentDate->format('l'));

                    // Verificamos si hay turno asignado en la plantilla para este día
                    // La plantilla debe guardar IDs de turnos: { "monday": 1, "tuesday": 2, "wednesday": null }
                    $shiftId = $template[$dayName] ?? null;

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

        return back()->with('success', 'Semana generada automáticamente basada en plantillas.');
    }
}