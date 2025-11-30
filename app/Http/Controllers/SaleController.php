<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SaleController extends Controller
{
    /**
     * Historial de Ventas (Listado General)
     */
    public function index(Request $request)
    {
        // Filtros básicos (por si quieres filtrar por fecha en el futuro)
        $query = Sale::query()->with(['user', 'dailyOperation']);

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // Ordenar: Lo más reciente primero
        $sales = $query->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Sale/Index', [
            'sales' => $sales,
            'filters' => $request->only(['date']),
        ]);
    }

    /**
     * Detalle de una Venta (Ticket)
     */
    public function show(Sale $sale)
    {
        // Cargamos relaciones necesarias para mostrar el ticket
        $sale->load([
            'details.product', // Items vendidos y sus nombres
            'user',            // Quién vendió
            'dailyOperation'   // Fecha de caja
        ]);

        return Inertia::render('Sale/Show', [
            'sale' => $sale
        ]);
    }
}