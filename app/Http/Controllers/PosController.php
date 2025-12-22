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
            // 1. Ordenamos por nombre alfabéticamente
            'products' => Product::where('is_active', true)
                ->where('is_sellable', true)
                ->with('inventories') // Cargamos la relación completa
                ->orderBy('name', 'asc') // Orden Alfabetico
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'barcode' => $product->barcode,
                        'price' => (float) $product->price,
                        'employee_price' => (float) $product->employee_price, // Enviamos precio empleado
                        // 2. Mapeamos el stock por ubicación: { location_id: cantidad }
                        'stocks' => $product->inventories->pluck('quantity', 'location_id'),
                        'track_inventory' => $product->track_inventory,
                        'image_url' => $product->getFirstMediaUrl('product_image', 'thumb'),
                    ];
                }),
            'locations' => Location::where('is_sales_point', true)->get(),
        ]);
    }

    // Abrir Caja
    public function openDay(Request $request)
    {
        $validated = $request->validate([
            'cash_start' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        if (DailyOperation::whereDate('date', now())->exists()) {
            return back()->with('error', 'Ya existe una operación para el día de hoy.');
        }

        DailyOperation::create([
            'date' => now()->toDateString(),
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
            'is_employee_sale' => 'boolean', // Nueva validación para la bandera
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $operation = DailyOperation::whereDate('date', now())->where('is_closed', false)->firstOrFail();

        DB::beginTransaction();
        try {
            $totalSale = 0;
            foreach ($validated['items'] as $item) {
                $totalSale += $item['quantity'] * $item['price'];
            }

            // Crear la venta con la bandera de empleado
            $sale = Sale::create([
                'daily_operation_id' => $operation->id,
                'user_id' => auth()->id(),
                'payment_method' => $validated['payment_method'],
                'total' => $totalSale,
                'is_employee_sale' => $request->boolean('is_employee_sale') // Guardamos la bandera
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                
                if ($product->track_inventory) {
                    $inventory = Inventory::firstOrCreate(
                        ['location_id' => $validated['location_id'], 'product_id' => $product->id],
                        ['quantity' => 0]
                    );

                    if ($inventory->quantity < $item['quantity']) {
                        throw new \Exception("Stock insuficiente para {$product->name}. Disponible: {$inventory->quantity}");
                    }

                    $inventory->decrement('quantity', $item['quantity']);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'from_location_id' => $validated['location_id'],
                        'to_location_id' => null,
                        'type' => StockMovementType::SALE,
                        'quantity' => $item['quantity'],
                        'user_id' => auth()->id(),
                        'notes' => "Venta #{$sale->id}"
                    ]);
                }

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

    // Cerrar Caja
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