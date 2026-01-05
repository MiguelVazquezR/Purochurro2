<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\Attendance;
use App\Models\DailyOperation;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SaleController extends Controller
{
    /**
     * Historial de Operaciones (Resumen por Día)
     */
   public function index(Request $request)
    {
        // Optimizamos la consulta cargando solo lo necesario
        $query = DailyOperation::query()
            ->with(['sales']); 

        // Filtro por fecha robusto
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        $history = $query->latest('date')
            ->paginate(10)
            ->withQueryString()
            ->through(function ($day) {
                // --- LÓGICA DE SEPARACIÓN DE VENTAS ---
                $totalPublic = $day->sales->where('is_employee_sale', false)->sum('total');
                $totalEmployee = $day->sales->where('is_employee_sale', true)->sum('total');
                $grandTotal = $totalPublic + $totalEmployee;

                // --- LÓGICA DE PERSONAL EN TURNO (Basada en ASISTENCIA REAL) ---
                $dateStr = $day->date->format('Y-m-d');

                // 1. Obtenemos quiénes realmente asistieron ese día
                $attendances = Attendance::with('employee.user')
                    ->whereDate('date', $dateStr)
                    ->get();
                
                // 2. Cargamos horarios solo para obtener colores/nombres de turno (cosmético)
                $schedules = WorkSchedule::with('shift')
                    ->whereDate('date', $dateStr)
                    ->get()
                    ->keyBy('employee_id');

                $staffList = $attendances->map(function ($attendance) use ($schedules) {
                    $emp = $attendance->employee;
                    if (!$emp) return null; 
                    
                    $schedule = $schedules->get($emp->id);
                    
                    return [
                        'id' => $emp->id,
                        'name' => $emp->full_name,
                        'initials' => substr($emp->first_name ?? 'X', 0, 1) . substr($emp->last_name ?? 'X', 0, 1),
                        'photo' => $emp->profile_photo_url ?? null,
                        'shift_color' => $schedule && $schedule->shift ? $schedule->shift->color : '#9ca3af',
                        'shift_name' => $schedule && $schedule->shift ? $schedule->shift->name : 'Asistencia registrada'
                    ];
                })->filter();

                return [
                    'id' => $day->id,
                    'date' => $day->date,
                    'is_closed' => $day->is_closed,
                    'staff_list' => $staffList->take(4)->values(),
                    'staff_count' => $staffList->count(),
                    'total_public' => $totalPublic,
                    'total_employee' => $totalEmployee,
                    'grand_total' => $grandTotal,
                    'cash_end' => $day->cash_end,
                ];
            });

        return Inertia::render('Sale/Index', [
            'salesHistory' => $history,
            'filters' => $request->only(['date']),
        ]);
    }

    /**
     * Detalle del Día
     */
    public function show(DailyOperation $dailyOperation)
    {
        $dailyOperation->load([
            'sales.user',
            'sales.details.product'
        ]);

        $date = $dailyOperation->date->format('Y-m-d');

        $schedules = WorkSchedule::with(['employee.user', 'shift'])
            ->whereDate('date', $date)
            ->get();

        $staffFromSchedule = $schedules->map(function ($schedule) {
            $employee = $schedule->employee;
            if($employee) {
                $employee->current_shift = $schedule->shift;
            }
            return $employee;
        })->filter();

        if ($staffFromSchedule->isEmpty()) {
            $dailyOperation->load(['staff.workSchedules' => function ($q) use ($date) {
                $q->whereDate('date', $date)->with('shift');
            }]);
            $staffFromSchedule = $dailyOperation->staff->map(function ($employee) {
                $employee->current_shift = $employee->workSchedules->first()?->shift;
                return $employee;
            });
        }

        $totalSales = $dailyOperation->sales()->sum('total');
        
        $refProduct = Product::find(1);
        $commissionBase = 0;
        
        if ($refProduct && $refProduct->price > 0) {
            $commissionBase = floor(($totalSales / ($refProduct->price * 10)) / 10) * 10;
        }

        return Inertia::render('Sale/Show', [
            'operation' => $dailyOperation,
            'scheduledStaff' => $staffFromSchedule->values(),
            'sales' => $dailyOperation->sales->sortByDesc('created_at')->values(),
            'totalSales' => $totalSales,
            'commissionBase' => $commissionBase
        ]);
    }

    /**
     * Cerrar la operación diaria
     */
    public function close(Request $request, DailyOperation $dailyOperation)
    {
        $validated = $request->validate([
            'cash_end' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $totalSales = $dailyOperation->sales()->sum('total');
            
            // 1. Cálculo de Comisión
            $refProduct = Product::find(1);
            $commissionBase = 0;
            if ($refProduct && $refProduct->price > 0) {
                $commissionBase = floor(($totalSales / ($refProduct->price * 10)) / 10) * 10;
            }

            // 2. Guardar Comisión en Asistencias del Día
            $affectedAttendances = Attendance::whereDate('date', $dailyOperation->date)
                ->update(['commission_amount' => $commissionBase]);

            // 3. Preparar notas de cierre
            $cashStart = floatval($dailyOperation->cash_start);
            $cashEndReal = floatval($validated['cash_end']);
            $expectedCash = $cashStart + $totalSales;
            $difference = $cashEndReal - $expectedCash;

            $finalNote = $validated['notes'] ? trim($validated['notes']) . "\n" : "";
            $finalNote .= "\n--- RESUMEN DE CORTE ---";
            $finalNote .= "\nFondo inicial:   $" . number_format($cashStart, 2);
            $finalNote .= "\nVentas totales:  $" . number_format($totalSales, 2);
            $finalNote .= "\n-------------------------";
            $finalNote .= "\nTotal esperado:  $" . number_format($expectedCash, 2);
            $finalNote .= "\nEfectivo real:   $" . number_format($cashEndReal, 2);

            $diffSymbol = $difference > 0 ? "+" : "";
            $diffLabel = $difference == 0 ? "(Cuadrado)" : ($difference > 0 ? "(Sobrante)" : "(Faltante)");

            $finalNote .= "\nDiferencia:      " . $diffSymbol . "$" . number_format($difference, 2) . " " . $diffLabel;
            $finalNote .= "\n\n--- COMISIÓN ---";
            $finalNote .= "\nPago por turno:  $" . number_format($commissionBase, 2);
            $finalNote .= "\nAsignada a:      " . $affectedAttendances . " empleados.";

            // =========================================================================
            // NUEVO 4: Pago Automático de Renta (Día 4 del mes)
            // =========================================================================
            if ($dailyOperation->date->day === 4) {
                // Verificar si ya se cobró hoy para evitar duplicados en caso de re-cierre (opcional)
                $rentExists = Expense::whereDate('date', $dailyOperation->date)
                    ->where('concept', 'Pago de Renta (Automático)')
                    ->exists();

                if (!$rentExists) {
                    Expense::create([
                        'concept' => 'Pago de Renta (Automático)',
                        'amount' => 52000,
                        'date' => $dailyOperation->date,
                        'notes' => 'Generado automáticamente al cierre de caja.',
                        'user_id' => auth()->id(),
                    ]);

                    $finalNote .= "\n\n--- GASTO AUTOMÁTICO ---";
                    $finalNote .= "\nSe registró el pago de Renta por $52,000.00";
                }
            }

            // =========================================================================
            // NUEVO 5: Traspaso de productos sobrantes (Carrito 1 -> Cocina)
            // =========================================================================
            $productsToReturn = [1, 2, 4, 5, 14, 15];
            $sourceSlug = 'carrito-1';
            $destSlug = 'cocina';

            $sourceLoc = Location::where('slug', $sourceSlug)->first();
            $destLoc = Location::where('slug', $destSlug)->first();

            if ($sourceLoc && $destLoc) {
                $transferredCount = 0;
                
                foreach ($productsToReturn as $productId) {
                    $sourceInv = Inventory::where('location_id', $sourceLoc->id)
                        ->where('product_id', $productId)
                        ->first();

                    // Si hay existencias en el carrito, las movemos todas
                    if ($sourceInv && $sourceInv->quantity > 0) {
                        $qtyToMove = $sourceInv->quantity;

                        // Destino (Cocina)
                        $destInv = Inventory::firstOrCreate(
                            ['location_id' => $destLoc->id, 'product_id' => $productId],
                            ['quantity' => 0]
                        );

                        // Movimiento
                        $sourceInv->decrement('quantity', $qtyToMove);
                        $destInv->increment('quantity', $qtyToMove);

                        // Registro Kardex
                        StockMovement::create([
                            'product_id' => $productId,
                            'from_location_id' => $sourceLoc->id,
                            'to_location_id' => $destLoc->id,
                            'quantity' => $qtyToMove,
                            'type' => StockMovementType::TRANSFER,
                            'user_id' => auth()->id(),
                            'notes' => 'Retorno automático al cierre de caja (Sobrante)'
                        ]);
                        
                        $transferredCount++;
                    }
                }
                
                if ($transferredCount > 0) {
                    $finalNote .= "\n\n--- INVENTARIO ---";
                    $finalNote .= "\nSe retornaron productos sobrantes del Carrito a Cocina.";
                }
            }

            // 6. Actualizar operación final
            $dailyOperation->update([
                'cash_end' => $validated['cash_end'],
                'is_closed' => true,
                'notes' => trim($finalNote)
            ]);

            DB::commit();
            return redirect()->back()->with('success', "Corte realizado. Comisión de $$commissionBase guardada.");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error al cerrar caja: ' . $e->getMessage());
        }
    }
}