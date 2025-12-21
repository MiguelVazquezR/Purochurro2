<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\DailyOperation;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SaleController extends Controller
{
    /**
     * Historial de Operaciones (Resumen por Día)
     */
     public function index(Request $request)
    {
        $query = DailyOperation::query()
            ->with(['staff.user', 'sales']); // Cargar user para la foto en caso de fallback

        // CORRECCIÓN: Validamos que 'date' tenga valor antes de filtrar
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        $history = $query->latest('date')
            ->paginate(10)
            ->withQueryString()
            ->through(function ($day) {
                $totalSales = $day->sales->sum('total');
                
                // --- LÓGICA DE PERSONAL EN TURNO (Basada en Horarios) ---
                $dateStr = $day->date->format('Y-m-d');
                
                // Cargamos 'employee.user' para acceder eficientemente a profile_photo_url
                $schedules = WorkSchedule::with(['employee.user', 'shift'])
                    ->whereDate('date', $dateStr)
                    ->get();
                
                $staffList = $schedules->map(function ($schedule) {
                    $emp = $schedule->employee;
                    return [
                        'id' => $emp->id,
                        'name' => $emp->full_name,
                        'initials' => substr($emp->first_name, 0, 1) . substr($emp->last_name, 0, 1),
                        'photo' => $emp->profile_photo_url, // Agregamos la URL de la foto
                        'shift_color' => $schedule->shift ? $schedule->shift->color : '#9ca3af',
                        'shift_name' => $schedule->shift ? $schedule->shift->name : 'Sin turno'
                    ];
                });

                // Fallback: Si no hay horarios, usar asignación manual de DailyOperation
                if ($staffList->isEmpty()) {
                    $staffList = $day->staff->map(function ($emp) {
                        return [
                            'id' => $emp->id,
                            'name' => $emp->full_name,
                            'initials' => substr($emp->first_name, 0, 1) . substr($emp->last_name, 0, 1),
                            'photo' => $emp->profile_photo_url, // Agregamos la URL de la foto
                            'shift_color' => '#e5e7eb',
                            'shift_name' => 'Asignación manual'
                        ];
                    });
                }

                return [
                    'id' => $day->id,
                    'date' => $day->date,
                    'is_closed' => $day->is_closed,
                    'staff_list' => $staffList->take(4), // Mostramos máx 4 avatares
                    'staff_count' => $staffList->count(),
                    'total_public' => $totalSales,
                    'total_employee' => 0,
                    'grand_total' => $totalSales,
                ];
            });

        return Inertia::render('Sale/Index', [
            'salesHistory' => $history,
            'filters' => $request->only(['date']),
        ]);
    }

    /**
     * Detalle del Día (Listado de ventas de una operación)
     */
    public function show(DailyOperation $dailyOperation)
    {
        // 1. Cargar ventas y sus detalles
        $dailyOperation->load([
            'sales.user',
            'sales.details.product'
        ]);

        // 2. OBTENER STAFF BASADO EN HORARIOS (WorkSchedule)
        // En lugar de confiar en $dailyOperation->staff, buscamos quiénes tenían turno este día.
        $date = $dailyOperation->date->format('Y-m-d');

        $schedules = WorkSchedule::with(['employee.user', 'shift'])
            ->whereDate('date', $date)
            ->get();

        // Transformamos estos horarios en una lista de empleados con su turno incrustado
        // para que la vista (Show.vue) pueda iterarlos fácilmente.
        $staffFromSchedule = $schedules->map(function ($schedule) {
            $employee = $schedule->employee;
            // Inyectamos el turno actual en una propiedad virtual para la vista
            $employee->current_shift = $schedule->shift;
            return $employee;
        });

        // Si no hay horarios, intentamos usar la relación 'staff' original como fallback
        if ($staffFromSchedule->isEmpty()) {
            $dailyOperation->load(['staff.workSchedules' => function ($q) use ($date) {
                $q->whereDate('date', $date)->with('shift');
            }]);
            $staffFromSchedule = $dailyOperation->staff->map(function ($employee) {
                $employee->current_shift = $employee->workSchedules->first()?->shift;
                return $employee;
            });
        }

        return Inertia::render('Sale/Show', [
            'operation' => $dailyOperation,
            // Pasamos la lista de empleados calculada manualmente
            'scheduledStaff' => $staffFromSchedule,
            'sales' => $dailyOperation->sales->sortByDesc('created_at')->values(),
            'totalSales' => $dailyOperation->sales->sum('total')
        ]);
    }

    /**
     * Cerrar la operación diaria (Corte de Caja)
     */
    public function close(Request $request, DailyOperation $dailyOperation)
    {
        // Validamos que se ingrese el efectivo contado
        $validated = $request->validate([
            'cash_end' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        // 1. Calcular Totales y Comisión
        $totalSales = $dailyOperation->sales()->sum('total');
        // Fórmula: (Total / 320) -> Bajado a la decena inferior
        $commissionBase = floor(($totalSales / 320) / 10) * 10;

        // 2. Calcular Balance de Caja (Diferencias)
        // Convertimos a float para asegurar cálculos matemáticos precisos
        $cashStart = floatval($dailyOperation->cash_start);
        $cashEndReal = floatval($validated['cash_end']);

        $expectedCash = $cashStart + $totalSales;
        $difference = $cashEndReal - $expectedCash;

        // 3. Preparar Nota Final Estructurada
        $finalNote = $validated['notes'] ? trim($validated['notes']) . "\n" : "";
        $finalNote .= "\n--- RESUMEN DE CORTE ---";
        $finalNote .= "\nFondo inicial:   $" . number_format($cashStart, 2);
        $finalNote .= "\nVentas totales:  $" . number_format($totalSales, 2);
        $finalNote .= "\n-------------------------";
        $finalNote .= "\nTotal esperado:  $" . number_format($expectedCash, 2);
        $finalNote .= "\nEfectivo real:   $" . number_format($cashEndReal, 2);

        // Indicador visual en texto para la diferencia
        $diffSymbol = $difference > 0 ? "+" : "";
        $diffLabel = $difference == 0 ? "(Cuadrado)" : ($difference > 0 ? "(Sobrante)" : "(Faltante)");

        $finalNote .= "\nDiferencia:      " . $diffSymbol . "$" . number_format($difference, 2) . " " . $diffLabel;
        $finalNote .= "\n\n--- COMISIÓN ---";
        $finalNote .= "\nPago por turno:  $" . number_format($commissionBase, 2);

        $dailyOperation->update([
            'cash_end' => $validated['cash_end'],
            'is_closed' => true,
            'notes' => trim($finalNote)
        ]);

        return redirect()->back()->with('success', 'Corte de caja realizado con éxito.');
    }
}