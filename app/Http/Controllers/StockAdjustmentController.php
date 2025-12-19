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
use Illuminate\Validation\ValidationException;

class StockAdjustmentController extends Controller
{
    public function index()
    {
        return Inertia::render('StockAdjustment/Index', [
            'locations' => Location::all(),
            'products' => Product::where('is_active', true)
                ->where('track_inventory', true)
                ->with('inventories')
                ->orderBy('name')
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'inventories' => $product->inventories->mapWithKeys(function ($inv) {
                            return [$inv->location_id => $inv->quantity];
                        }),
                        'image_url' => $product->getFirstMediaUrl('product_image', 'thumb'),
                    ];
                }),
            'types' => [
                ['value' => StockMovementType::PURCHASE->value, 'label' => 'Compra / Entrada (+)', 'color' => 'green'],
                ['value' => StockMovementType::ADJUSTMENT_IN->value, 'label' => 'Ajuste Entrada (+)', 'color' => 'blue'],
                ['value' => StockMovementType::ADJUSTMENT_OUT->value, 'label' => 'Ajuste Salida (-)', 'color' => 'yellow'],
                ['value' => StockMovementType::WASTE->value, 'label' => 'Merma / Caducado (-)', 'color' => 'red'],
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
        // Definir si es entrada o salida basado en el Enum
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
                    throw ValidationException::withMessages([
                        'quantity' => "Stock insuficiente. Actual: {$inventory->quantity}"
                    ]);
                }
                $inventory->decrement('quantity', $validated['quantity']);
            } else {
                $inventory->increment('quantity', $validated['quantity']);
            }

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
            return back()->with('success', 'Movimiento de inventario registrado.');

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
}