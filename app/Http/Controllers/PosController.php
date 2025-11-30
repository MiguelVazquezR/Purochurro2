<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\DailyOperation;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PosController extends Controller
{
    // Vista Principal del POS
    public function index()
    {
        // Buscar si hay una operación abierta hoy
        $todayOperation = DailyOperation::whereDate('date', now())
            ->where('is_closed', false)
            ->first();

        if (!$todayOperation) {
            return Inertia::render('Pos/OpenDay');
        }

        return Inertia::render('Pos/Terminal', [
            'operation' => $todayOperation,
            'products' => Product::where('is_active', true)->where('is_sellable', true)->get(),
            'locations' => Location::where('is_sales_point', true)->get(), // Solo puntos de venta
        ]);
    }

    // Abrir Caja
    public function openDay(Request $request)
    {
        $validated = $request->validate([
            'cash_start' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        // Verificar que no exista una ya abierta hoy
        if (DailyOperation::whereDate('date', now())->exists()) {
            return back()->with('error', 'Ya existe una operación para el día de hoy.');
        }

        DailyOperation::create([
            'date' => now()->toDateString(), // Guardar solo fecha, sin hora
            'cash_start' => $validated['cash_start'],
            'notes' => $validated['notes'],
            'is_closed' => false
        ]);

        return redirect()->route('pos.index');
    }

    // Registrar Venta
    public function storeSale(Request $request)
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'payment_method' => 'required|string|in:cash,card,transfer',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $operation = DailyOperation::whereDate('date', now())->where('is_closed', false)->firstOrFail();

        DB::beginTransaction();
        try {
            // 1. Crear Cabecera de Venta
            $totalSale = 0;
            foreach ($validated['items'] as $item) {
                $totalSale += $item['quantity'] * $item['price'];
            }

            $sale = Sale::create([
                'daily_operation_id' => $operation->id,
                'user_id' => auth()->id(),
                'payment_method' => $validated['payment_method'],
                'total' => $totalSale
            ]);

            // 2. Procesar Detalles y Stock
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                
                // Chequeo de Stock si el producto lo requiere
                if ($product->track_inventory) {
                    $inventory = Inventory::firstOrCreate(
                        ['location_id' => $validated['location_id'], 'product_id' => $product->id],
                        ['quantity' => 0]
                    );

                    if ($inventory->quantity < $item['quantity']) {
                        throw new \Exception("Stock insuficiente para {$product->name}. Disponible: {$inventory->quantity}");
                    }

                    $inventory->decrement('quantity', $item['quantity']);

                    // Registrar Movimiento de Stock
                    StockMovement::create([
                        'product_id' => $product->id,
                        'from_location_id' => $validated['location_id'], // Sale de aquí
                        'to_location_id' => null, // Sale del sistema (cliente)
                        'type' => StockMovementType::SALE,
                        'quantity' => $item['quantity'],
                        'user_id' => auth()->id(),
                        'notes' => "Venta #{$sale->id}"
                    ]);
                }

                // Guardar Detalle
                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'subtotal' => $item['quantity'] * $item['price']
                ]);
            }

            DB::commit();
            return back()->with('success', 'Venta registrada con éxito.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    // Cerrar Caja (Corte)
    public function closeDay(Request $request)
    {
        $validated = $request->validate([
            'cash_end' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        $operation = DailyOperation::whereDate('date', now())->where('is_closed', false)->firstOrFail();

        $operation->update([
            'cash_end' => $validated['cash_end'],
            'notes' => $validated['notes'],
            'is_closed' => true
        ]);

        return redirect()->route('dashboard')->with('success', 'Corte de caja realizado correctamente.');
    }
}