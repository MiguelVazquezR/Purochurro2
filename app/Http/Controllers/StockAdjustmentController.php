<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class StockAdjustmentController extends Controller
{
    public function index()
    {
        return Inertia::render('StockAdjustment/Index', [
            'locations' => Location::all(),
            'products' => Product::where('is_active', true)->where('track_inventory', true)->orderBy('name')->get(),
            'types' => [
                ['value' => StockMovementType::PURCHASE->value, 'label' => 'Compra (Entrada)'],
                ['value' => StockMovementType::ADJUSTMENT_IN->value, 'label' => 'Ajuste Manual (+)'],
                ['value' => StockMovementType::ADJUSTMENT_OUT->value, 'label' => 'Ajuste Manual (-)'],
                ['value' => StockMovementType::WASTE->value, 'label' => 'Merma / Caducado (-)'],
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'product_id' => 'required|exists:products,id',
            'type' => ['required', Rule::enum(StockMovementType::class)],
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:255'
        ]);

        $type = StockMovementType::from($validated['type']);
        $isEntry = in_array($type, [StockMovementType::PURCHASE, StockMovementType::ADJUSTMENT_IN]);
        
        DB::beginTransaction();
        try {
            $inventory = Inventory::firstOrCreate(
                ['location_id' => $validated['location_id'], 'product_id' => $validated['product_id']],
                ['quantity' => 0]
            );

            // Validar Stock si es salida
            if (!$isEntry) {
                if ($inventory->quantity < $validated['quantity']) {
                    return back()->with('error', "Stock insuficiente. Actual: {$inventory->quantity}");
                }
                $inventory->decrement('quantity', $validated['quantity']);
            } else {
                $inventory->increment('quantity', $validated['quantity']);
            }

            // Registrar Movimiento
            // Si es entrada: from=NULL, to=Location
            // Si es salida: from=Location, to=NULL
            StockMovement::create([
                'product_id' => $validated['product_id'],
                'from_location_id' => $isEntry ? null : $validated['location_id'],
                'to_location_id' => $isEntry ? $validated['location_id'] : null,
                'type' => $type,
                'quantity' => $validated['quantity'],
                'user_id' => auth()->id(),
                'notes' => $validated['notes']
            ]);

            DB::commit();
            return back()->with('success', 'Movimiento registrado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al procesar: ' . $e->getMessage());
        }
    }
}