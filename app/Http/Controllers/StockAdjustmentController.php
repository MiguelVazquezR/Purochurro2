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
                ['value' => StockMovementType::PURCHASE->value, 'label' => 'Compra / Entrada (+)', 'color' => 'green', 'icon' => 'pi-shopping-cart'],
                ['value' => StockMovementType::ADJUSTMENT_IN->value, 'label' => 'Ajuste Entrada (+)', 'color' => 'blue', 'icon' => 'pi-plus-circle'],
                ['value' => StockMovementType::ADJUSTMENT_OUT->value, 'label' => 'Ajuste Salida (-)', 'color' => 'yellow', 'icon' => 'pi-minus-circle'],
                ['value' => StockMovementType::WASTE->value, 'label' => 'Merma / Caducado (-)', 'color' => 'red', 'icon' => 'pi-trash'],
            ]
        ]);
    }

    public function store(Request $request)
    {
        // 1. Validar Cabecera y Lista de Items
        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'type' => ['required', Rule::enum(StockMovementType::class)],
            'notes' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $type = StockMovementType::from($validated['type']);
        $isEntry = in_array($type, [StockMovementType::PURCHASE, StockMovementType::ADJUSTMENT_IN]);
        
        DB::beginTransaction();
        try {
            // 2. Procesar cada item
            foreach ($validated['items'] as $item) {
                $inventory = Inventory::firstOrCreate(
                    ['location_id' => $validated['location_id'], 'product_id' => $item['product_id']],
                    ['quantity' => 0]
                );

                // Validar Stock si es salida
                if (!$isEntry) {
                    if ($inventory->quantity < $item['quantity']) {
                        $productName = Product::find($item['product_id'])->name;
                        throw ValidationException::withMessages([
                            'items' => "Stock insuficiente para '{$productName}'. Actual: {$inventory->quantity}"
                        ]);
                    }
                    $inventory->decrement('quantity', $item['quantity']);
                } else {
                    $inventory->increment('quantity', $item['quantity']);
                }

                // 3. Registrar Movimiento
                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'from_location_id' => $isEntry ? null : $validated['location_id'],
                    'to_location_id' => $isEntry ? $validated['location_id'] : null,
                    'type' => $type,
                    'quantity' => $item['quantity'],
                    'user_id' => auth()->id(),
                    'notes' => $validated['notes'] ?? 'Ajuste Múltiple'
                ]);
            }

            DB::commit();
            return back()->with('success', 'Movimientos de inventario registrados correctamente.');

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
}