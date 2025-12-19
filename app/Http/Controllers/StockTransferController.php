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
                        // Extra para UI
                        'image_url' => $product->getFirstMediaUrl('product_image', 'thumb'),
                    ];
                })
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'from_location_id' => 'required|exists:locations,id|different:to_location_id',
            'to_location_id' => 'required|exists:locations,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:255'
        ]);

        DB::beginTransaction();
        try {
            $product = Product::find($validated['product_id']);

            // 1. Verificar Origen
            $sourceInventory = Inventory::firstOrCreate(
                ['location_id' => $validated['from_location_id'], 'product_id' => $validated['product_id']],
                ['quantity' => 0]
            );

            if ($sourceInventory->quantity < $validated['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => "Stock insuficiente en origen. Disponible: {$sourceInventory->quantity}"
                ]);
            }

            // 2. Asegurar Destino
            $destInventory = Inventory::firstOrCreate(
                ['location_id' => $validated['to_location_id'], 'product_id' => $validated['product_id']],
                ['quantity' => 0]
            );

            // 3. Mover
            $sourceInventory->decrement('quantity', $validated['quantity']);
            $destInventory->increment('quantity', $validated['quantity']);

            // 4. Registrar Kardex
            StockMovement::create([
                'product_id' => $validated['product_id'],
                'from_location_id' => $validated['from_location_id'],
                'to_location_id' => $validated['to_location_id'],
                'quantity' => $validated['quantity'],
                'type' => StockMovementType::TRANSFER,
                'user_id' => auth()->id(),
                'notes' => $validated['notes']
            ]);

            DB::commit();
            return back()->with('success', "Traspaso de {$product->name} realizado con éxito.");

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error inesperado: ' . $e->getMessage());
        }
    }
}