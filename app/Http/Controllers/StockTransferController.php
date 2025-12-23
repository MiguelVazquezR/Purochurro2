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
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller
{
    public function index()
    {
        return Inertia::render('StockTransfer/Index', [
            'locations' => Location::all(),
            // Cargamos 'inventories' para que el frontend sepa cuánto hay en cada lugar
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
                })
        ]);
    }

    public function store(Request $request)
    {
        // 1. Validamos la estructura general y el array de items
        $validated = $request->validate([
            'from_location_id' => 'required|exists:locations,id|different:to_location_id',
            'to_location_id' => 'required|exists:locations,id',
            'notes' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            // 2. Procesamos cada item del array
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);

                // A. Verificar Origen
                $sourceInventory = Inventory::firstOrCreate(
                    ['location_id' => $validated['from_location_id'], 'product_id' => $item['product_id']],
                    ['quantity' => 0]
                );

                if ($sourceInventory->quantity < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => "Stock insuficiente para '{$product->name}'. Disponible: {$sourceInventory->quantity}"
                    ]);
                }

                // B. Asegurar Destino
                $destInventory = Inventory::firstOrCreate(
                    ['location_id' => $validated['to_location_id'], 'product_id' => $item['product_id']],
                    ['quantity' => 0]
                );

                // C. Mover Stock
                $sourceInventory->decrement('quantity', $item['quantity']);
                $destInventory->increment('quantity', $item['quantity']);

                // D. Registrar Kardex individualmente para trazabilidad
                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'from_location_id' => $validated['from_location_id'],
                    'to_location_id' => $validated['to_location_id'],
                    'quantity' => $item['quantity'],
                    'type' => StockMovementType::TRANSFER,
                    'user_id' => auth()->id(),
                    'notes' => $validated['notes'] ?? 'Traspaso Múltiple'
                ]);
            }

            DB::commit();
            return back()->with('success', "Se han traspasado " . count($validated['items']) . " productos correctamente.");

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error inesperado: ' . $e->getMessage());
        }
    }
}