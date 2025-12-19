<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\DailyOperation;
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
            ->with(['staff', 'sales']);

        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }

        $history = $query->latest('date')
            ->paginate(10)
            ->withQueryString()
            ->through(function ($day) {
                $totalSales = $day->sales->sum('total');
                return [
                    'id' => $day->id,
                    'date' => $day->date,
                    'is_closed' => $day->is_closed,
                    'staff_names' => $day->staff->pluck('name')->unique()->values()->take(3),
                    'staff_count' => $day->staff->count(),
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
        // Cargamos:
        // 1. Staff con su ubicación (pivot)
        // 2. Ventas con el usuario que vendió y los detalles (productos)
        $dailyOperation->load([
            'staff',
            'sales.user', 
            'sales.details.product'
        ]);

        return Inertia::render('Sale/Show', [
            'operation' => $dailyOperation,
            // Enviamos las ventas ordenadas por hora (más reciente arriba)
            'sales' => $dailyOperation->sales->sortByDesc('created_at')->values(),
            'totalSales' => $dailyOperation->sales->sum('total')
        ]);
    }

    /**
     * Cerrar la operación diaria (Corte de Caja)
     */
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